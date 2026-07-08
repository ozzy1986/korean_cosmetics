#!/bin/bash
set -e
SITE_ROOT=/var/www/ru-atomy.ru
WP="/usr/local/bin/wp --allow-root"

$WP plugin activate atomy-core --path=$SITE_ROOT
$WP theme activate atomy-ru --path=$SITE_ROOT

SHOP_ID=$($WP option get woocommerce_shop_page_id --path=$SITE_ROOT)
if [ "$SHOP_ID" = "0" ] || [ -z "$SHOP_ID" ]; then
  SHOP_ID=$($WP post create --post_type=page --post_title=Shop --post_status=publish --porcelain --path=$SITE_ROOT)
  $WP option update woocommerce_shop_page_id "$SHOP_ID" --path=$SITE_ROOT
fi

REQ_ID=$($WP post list --post_type=page --name=request --field=ID --path=$SITE_ROOT 2>/dev/null || true)
if [ -z "$REQ_ID" ]; then
  $WP post create --post_type=page --post_title=Request --post_name=request --post_status=publish --post_content='[atomy_request_form]' --porcelain --path=$SITE_ROOT
else
  $WP post update "$REQ_ID" --post_content='[atomy_request_form]' --path=$SITE_ROOT
fi

$WP option update show_on_front page --path=$SITE_ROOT
$WP option update page_on_front "$SHOP_ID" --path=$SITE_ROOT

$WP atomy settings:apply-secrets --file=/root/ru-atomy-secrets.json --path=$SITE_ROOT

echo "--- telegram resolve ---"
$WP atomy telegram:resolve-chat --path=$SITE_ROOT || echo TELEGRAM_RESOLVE_FAILED

$WP plugin list --path=$SITE_ROOT --status=active --format=table
$WP theme list --path=$SITE_ROOT --status=active --format=table
echo ALL_WP_STEPS_DONE
