<?php
/**
 * Unit tests for Atomy_Seo_Text (pure PHP, no WordPress).
 */

declare( strict_types=1 );

require __DIR__ . '/run.php';
require __DIR__ . '/../../wp-content/plugins/atomy-core/includes/services/class-seo-text.php';

$seo = new Atomy_Seo_Text();

// 1. Full data intro 100-300 chars with name and price.
$intro_full = $seo->product_intro(
	array(
		'name'         => 'HemoHIM',
		'category'     => 'Здоровье',
		'tags'         => array( 'иммунитет', 'натуральный состав' ),
		'badges'       => array( 'Halal', 'charity' ),
		'member_price' => 55000,
		'pv'           => 70000,
	)
);
assert_true( mb_strlen( $intro_full ) >= 100, 'full intro >= 100 chars' );
assert_true( mb_strlen( $intro_full ) <= 300, 'full intro <= 300 chars' );
assert_true( str_contains( $intro_full, 'HemoHIM' ), 'full intro contains name' );
assert_true( str_contains( $intro_full, '55' ) || str_contains( $intro_full, '55000' ), 'full intro contains price' );

// 2. No tags still >= 100 chars.
$intro_no_tags = $seo->product_intro(
	array(
		'name'         => 'Пенка для умывания',
		'category'     => 'Уход за кожей',
		'tags'         => array(),
		'badges'       => array(),
		'member_price' => 3200,
		'pv'           => 1500,
	)
);
assert_true( mb_strlen( $intro_no_tags ) >= 100, 'no tags intro >= 100 chars' );
assert_true( mb_strlen( $intro_no_tags ) <= 300, 'no tags intro <= 300 chars' );

// 3. Long name/tags clamped <= 300 without mid-word cut.
$long_name = str_repeat( 'Супердлинноеназваниепродукта ', 8 );
$long_tags = array_map(
	static fn( $i ) => 'тег' . $i . str_repeat( 'оченьдлинный', 3 ),
	range( 1, 12 )
);
$intro_long = $seo->product_intro(
	array(
		'name'         => trim( $long_name ),
		'category'     => 'Категория с очень длинным названием для проверки обрезки',
		'tags'         => $long_tags,
		'badges'       => array( 'Halal', 'charity' ),
		'member_price' => 99999,
		'pv'           => 50000,
	)
);
assert_true( mb_strlen( $intro_long ) <= 300, 'long intro <= 300 chars' );
$long_data = array(
	'name'         => trim( $long_name ),
	'category'     => 'Категория с очень длинным названием для проверки обрезки',
	'tags'         => $long_tags,
	'badges'       => array( 'Halal', 'charity' ),
	'member_price' => 99999,
	'pv'           => 50000,
);
$full_long = $seo->product_intro( $long_data, 10000 );
if ( mb_strlen( $full_long ) > 300 ) {
	$prefix = mb_substr( $full_long, 0, mb_strlen( $intro_long ) );
	assert_true(
		$prefix === $intro_long || rtrim( $prefix, ".,;:!?«»\"'-" ) === $intro_long,
		'long intro is prefix of full text'
	);
	$after = mb_substr( $full_long, mb_strlen( rtrim( $prefix ) ), 1 );
	assert_true( '' === $after || preg_match( '/\s/u', $after ), 'long intro not cut mid-word' );
}

// 4. member_price=0 no price sentence, valid length.
$intro_no_price = $seo->product_intro(
	array(
		'name'         => 'Пробник Atomy',
		'category'     => 'Наборы',
		'tags'         => array( 'пробник' ),
		'badges'       => array(),
		'member_price' => 0,
		'pv'           => 0,
	)
);
assert_true( ! preg_match( '/\d+\s*₽/u', $intro_no_price ), 'no price sentence when member_price=0' );
assert_true( mb_strlen( $intro_no_price ) >= 100, 'no price intro >= 100 chars' );
assert_true( mb_strlen( $intro_no_price ) <= 300, 'no price intro <= 300 chars' );

// 5. Cyrillic mb_strlen 150 chars clamp.
$cyrillic_source = 'Абвгдежзийклмнопрстуфхцчшщъыьэюя ' . str_repeat( 'ёЁ', 40 );
$clamped_cyrillic = $seo->clamp( $cyrillic_source, 150 );
assert_true( mb_strlen( $clamped_cyrillic ) <= 150, 'cyrillic clamp <= 150' );
assert_true( mb_strlen( $clamped_cyrillic ) > 0, 'cyrillic clamp not empty' );

// 6. enhance_description_html.
$alt_base = 'HemoHIM';
$html_mixed = '<p>Текст</p><img src="a.jpg"><img src="b.jpg" alt="Своё описание"><span>ok</span>';
$enhanced   = $seo->enhance_description_html( $html_mixed, $alt_base );
assert_true( str_contains( $enhanced, 'alt="' . $alt_base . ' — фото 1"' ), 'empty alt filled' );
assert_true( str_contains( $enhanced, 'alt="Своё описание"' ), 'existing alt kept' );
assert_true( str_contains( $enhanced, 'loading="lazy"' ), 'loading lazy added' );
assert_true( str_contains( $enhanced, 'decoding="async"' ), 'decoding async added' );
assert_true( str_contains( $enhanced, '<span>ok</span>' ), 'non-img unchanged' );
$broken = $seo->enhance_description_html( '<img src=x unclosed', $alt_base );
assert_true( is_string( $broken ), 'broken HTML returns string' );

// 7. category_intro has name and count.
$cat_intro = $seo->category_intro( 'Здоровье', 42 );
assert_true( str_contains( $cat_intro, 'Здоровье' ), 'category intro has name' );
assert_true( str_contains( $cat_intro, '42' ), 'category intro has count' );

test_summary();
