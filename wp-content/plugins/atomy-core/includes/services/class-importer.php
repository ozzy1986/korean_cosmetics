<?php
/**
 * WooCommerce catalog importer from scraped JSON.
 *
 * @package Atomy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atomy_Core_Importer {

	public function register(): void {
		// CLI only.
	}

	public function import_from_dir( string $data_dir ): array {
		$data_dir = trailingslashit( $data_dir );
		$categories_file = $data_dir . 'categories.json';
		$products_file   = $data_dir . 'products.json';

		if ( ! file_exists( $categories_file ) || ! file_exists( $products_file ) ) {
			return array( 'error' => 'Missing categories.json or products.json' );
		}

		$categories = json_decode( (string) file_get_contents( $categories_file ), true );
		$products   = json_decode( (string) file_get_contents( $products_file ), true );

		if ( ! is_array( $categories ) || ! is_array( $products ) ) {
			return array( 'error' => 'Invalid JSON data' );
		}

		$term_map = $this->import_categories( $categories );
		$sku_terms = $this->build_sku_term_map( $categories, $term_map );
		$stats    = $this->import_products( $products, $term_map, $sku_terms, $data_dir );

		return array(
			'categories' => count( $term_map ),
			'products'   => $stats,
		);
	}

	private function import_categories( array $categories ): array {
		$map = array();
		foreach ( $categories as $cat ) {
			$slug = 'cat-' . sanitize_title( $cat['disp_ctg_no'] ?? 'unknown' );
			$name = sanitize_text_field( $cat['name'] ?? 'Category' );
			$term = term_exists( $slug, 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
			} else {
				wp_update_term( (int) $term['term_id'], 'product_cat', array( 'name' => $name ) );
			}
			if ( is_wp_error( $term ) ) {
				continue;
			}
			$term_id = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			update_term_meta( $term_id, '_atomy_disp_ctg_no', $cat['disp_ctg_no'] ?? '' );
			$map[ $cat['disp_ctg_no'] ] = $term_id;
		}
		return $map;
	}

	/**
	 * Build SKU -> [term_id, ...] map from each category's product_ids list.
	 */
	private function build_sku_term_map( array $categories, array $term_map ): array {
		$sku_terms = array();
		foreach ( $categories as $cat ) {
			$disp = $cat['disp_ctg_no'] ?? '';
			if ( ! isset( $term_map[ $disp ] ) ) {
				continue;
			}
			$term_id = (int) $term_map[ $disp ];
			foreach ( (array) ( $cat['product_ids'] ?? array() ) as $sku ) {
				$sku = (string) $sku;
				$sku_terms[ $sku ][ $term_id ] = $term_id;
			}
		}
		return $sku_terms;
	}

	private function import_products( array $products, array $term_map, array $sku_terms, string $data_dir ): array {
		$created = 0;
		$updated = 0;
		$seen    = array();

		wp_suspend_cache_addition( true );
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		foreach ( $products as $row ) {
			$sku = sanitize_text_field( $row['goods_no'] ?? '' );
			if ( '' === $sku ) {
				continue;
			}
			$seen[] = $sku;
			$product_id = wc_get_product_id_by_sku( $sku );
			$is_new     = ! $product_id;

			if ( $is_new ) {
				$product_id = wp_insert_post(
					array(
						'post_type'   => 'product',
						'post_status' => 'publish',
						'post_title'  => sanitize_text_field( $row['name'] ?? $sku ),
					)
				);
				if ( is_wp_error( $product_id ) || ! $product_id ) {
					continue;
				}
				++$created;
			} else {
				wp_update_post(
					array(
						'ID'          => $product_id,
						'post_title'  => sanitize_text_field( $row['name'] ?? $sku ),
						'post_status' => 'publish',
					)
				);
				++$updated;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$member = (float) ( $row['member_price'] ?? 0 );
			$reg    = (float) ( $row['reg_price'] ?? 0 );
			$pv     = (float) ( $row['pv'] ?? 0 );

			$cat_ids = array();
			if ( isset( $sku_terms[ $sku ] ) ) {
				$cat_ids = array_values( $sku_terms[ $sku ] );
			}
			foreach ( (array) ( $row['category_ids'] ?? array() ) as $disp_id ) {
				if ( isset( $term_map[ $disp_id ] ) ) {
					$cat_ids[] = (int) $term_map[ $disp_id ];
				}
			}
			$cat_ids = array_values( array_unique( array_map( 'intval', $cat_ids ) ) );

			$product->set_sku( $sku );
			$product->set_regular_price( $member > 0 ? (string) $member : (string) $reg );
			$product->set_manage_stock( false );
			$product->set_stock_status( 'instock' );
			$product->set_catalog_visibility( 'visible' );
			$clean_desc = $this->clean_description_html( (string) ( $row['description_html'] ?? '' ) );
			$seo_text   = new Atomy_Seo_Text();
			$product_name = sanitize_text_field( $row['name'] ?? $sku );
			$cat_name   = '';
			if ( $cat_ids ) {
				$first_term = get_term( (int) $cat_ids[0], 'product_cat' );
				if ( $first_term && ! is_wp_error( $first_term ) ) {
					$cat_name = $first_term->name;
				}
			}
			$intro = $seo_text->product_intro(
				array(
					'name'         => $product_name,
					'category'     => $cat_name,
					'tags'         => (array) ( $row['tags'] ?? array() ),
					'badges'       => (array) ( $row['badges'] ?? array() ),
					'member_price' => $member,
					'pv'           => $pv,
				)
			);
			$product->set_description( $seo_text->enhance_description_html( $clean_desc, $product_name ) );
			$product->set_short_description( $intro );
			$product->update_meta_data( '_atomy_reg_price', $reg );
			$product->update_meta_data( '_atomy_member_price', $member );
			$product->update_meta_data( '_atomy_pv', $pv );
			$product->update_meta_data( '_atomy_goods_no', $sku );
			$product->update_meta_data( '_atomy_badges', $row['badges'] ?? array() );
			$product->save();

			if ( $cat_ids ) {
				wp_set_object_terms( $product_id, $cat_ids, 'product_cat' );
			}

			if ( ! empty( $row['tags'] ) ) {
				wp_set_object_terms( $product_id, array_map( 'sanitize_text_field', $row['tags'] ), 'product_tag' );
			}

			$this->attach_images( $product_id, $row, $data_dir );
		}

		wp_suspend_cache_addition( false );

		$hidden = $this->mark_missing_hidden( $seen );

		return array(
			'created' => $created,
			'updated' => $updated,
			'hidden'  => $hidden,
			'total'   => count( $seen ),
		);
	}

	/**
	 * Strip Atomy accordion chrome from the scraped description and keep only the
	 * real product-info content (#contentBuilderContents): images and text.
	 */
	private function clean_description_html( string $html ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}

		// Drop HTML comments (including Korean developer notes).
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );

		if ( ! class_exists( 'DOMDocument' ) ) {
			return wp_kses_post( $html );
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML(
			'<?xml encoding="UTF-8"?><div id="atomy-desc-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Prefer the genuine content container; fall back to our wrapper.
		$target = null;
		$nodes  = $xpath->query( '//*[@id="contentBuilderContents"]' );
		if ( $nodes && $nodes->length ) {
			$target = $nodes->item( 0 );
		} else {
			$roots = $xpath->query( '//*[@id="atomy-desc-root"]' );
			$target = ( $roots && $roots->length ) ? $roots->item( 0 ) : null;
		}
		if ( ! $target ) {
			return wp_kses_post( $html );
		}

		// Remove interactive/structural chrome that has no meaning as content.
		foreach ( array( 'button', 'script', 'style' ) as $tag ) {
			$els = $xpath->query( './/' . $tag, $target );
			if ( ! $els ) {
				continue;
			}
			for ( $i = $els->length - 1; $i >= 0; $i-- ) {
				$el = $els->item( $i );
				if ( $el->parentNode ) {
					$el->parentNode->removeChild( $el );
				}
			}
		}

		$inner = '';
		foreach ( iterator_to_array( $target->childNodes ) as $child ) {
			$inner .= $dom->saveHTML( $child );
		}

		$inner = trim( $inner );
		return '' !== $inner ? wp_kses_post( $inner ) : wp_kses_post( $html );
	}

	private function attach_images( int $product_id, array $row, string $data_dir ): void {
		// Idempotent: once a product has its featured image, skip re-sideloading
		// on subsequent imports to avoid duplicate media bloat. New products still import.
		if ( get_post_thumbnail_id( $product_id ) ) {
			return;
		}

		$paths = $row['local_gallery_images'] ?? array();
		$urls  = $row['gallery_images'] ?? array();
		$attachment_ids = array();
		$seen_files     = array();

		foreach ( $paths as $rel_path ) {
			$full = trailingslashit( $data_dir ) . ltrim( $rel_path, '/' );
			if ( ! file_exists( $full ) ) {
				continue;
			}
			$key = md5( $full );
			if ( isset( $seen_files[ $key ] ) ) {
				continue;
			}
			$seen_files[ $key ] = true;
			$attachment_id = $this->sideload_local_image( $full, $product_id );
			if ( $attachment_id ) {
				$attachment_ids[] = $attachment_id;
			}
		}

		foreach ( $urls as $url ) {
			$url = (string) $url;
			if ( '' === $url ) {
				continue;
			}
			$key = md5( $url );
			if ( isset( $seen_files[ $key ] ) ) {
				continue;
			}
			$seen_files[ $key ] = true;
			$attachment_id = $this->sideload_remote_image( $url, $product_id );
			if ( $attachment_id ) {
				$attachment_ids[] = $attachment_id;
			}
		}

		$attachment_ids = array_values( array_unique( array_filter( $attachment_ids ) ) );

		if ( $attachment_ids ) {
			set_post_thumbnail( $product_id, $attachment_ids[0] );
			if ( count( $attachment_ids ) > 1 ) {
				update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_slice( $attachment_ids, 1 ) ) );
			} else {
				delete_post_meta( $product_id, '_product_image_gallery' );
			}
		}
	}

	private function sideload_remote_image( string $url, int $post_id ): int {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}
		return (int) $attachment_id;
	}

	private function sideload_local_image( string $file, int $post_id ): int {
		$filename = basename( $file );
		$upload   = wp_upload_dir();
		$dest     = trailingslashit( $upload['path'] ) . wp_unique_filename( $upload['path'], $filename );
		if ( ! copy( $file, $dest ) ) {
			return 0;
		}
		$filetype = wp_check_filetype( $dest );
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $dest, $post_id );
		if ( ! $attach_id ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $attach_id, $dest );
		wp_update_attachment_metadata( $attach_id, $meta );
		return (int) $attach_id;
	}

	private function mark_missing_hidden( array $seen_skus ): int {
		$hidden = 0;
		$query  = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_atomy_goods_no',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		foreach ( $query->posts as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}
			$sku = $product->get_sku();
			if ( $sku && ! in_array( $sku, $seen_skus, true ) ) {
				$product->set_stock_status( 'outofstock' );
				$product->set_catalog_visibility( 'hidden' );
				$product->save();
				++$hidden;
			}
		}
		return $hidden;
	}
}
