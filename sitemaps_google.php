<?php
/*
 * Генератор xml карт сайта для проекта ВЕСТИ
 *
 */

ini_set( 'display_errors', 1 );
error_reporting( E_ERROR );

ini_set( 'memory_limit', '128M' );
set_time_limit( 0 );

$_SERVER['DOCUMENT_ROOT'] = str_replace( '/exec/cron', '', dirname( realpath( __FILE__ ) ) );
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/globals.php';

$attr = array(
    'langid'   => $langid,
    'path'     => $_SERVER['DOCUMENT_ROOT'] . '/pub/sitemaps/',
    'tmp_path' => $_SERVER['DOCUMENT_ROOT'] . '/cache/sitemaps/',
    'only_last_page' => in_array( '-last_page', $argv )
);

if( !file_exists( $attr['tmp_path'] ) ) {
    mkdir( $attr['tmp_path'], 0777 );
}

if( !file_exists( $attr['path'] ) ) {
    mkdir( $attr['path'], 0777 );
}

# google sitemaps 14.11.2018
$year = date('Y');
$month = date('m');
$day = date('d') - 2;
$ds = strtotime($year . '-' . $month . '-'.$day.'T00:00:00');
$filename = 'sitemap_google';
    $query = $db->exquery( "SELECT absnum, alias, category, changed, title FROM articles AS a WHERE approved = 1 AND type & {noindex|t} = 0 AND langid={l|i} AND EXISTS( SELECT absnum FROM pages WHERE absnum = a.category AND approved = 1 AND type & {t|t} > 0 ) AND adate >= {ds|i} ORDER BY adate ASC", array(
        'l' => $attr['langid'],
        't' => $phrase->bit( 'sunsite/pages_props/news' ),
        'noindex' => $phrase->bit("sunsite/articles_props/noindex"),
        'ds' => $ds,
    ) );

    file_put_contents( $attr['tmp_path'] . $filename . '.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' );
    while( $row = $query->nextAssoc() ) {
        build_sitemap_items( 'google', $row, $attr['tmp_path'] . $filename . '.xml' );
    }

    file_put_contents( $attr['tmp_path'] . $filename . '.xml', '</urlset>', FILE_APPEND );
    echo ' > ' . $attr['tmp_path'] . $filename . '.xml - ready' . PHP_EOL;

    unset( $query );

// move files in pub/sitemaps
$xml_files = glob( $attr['tmp_path'] . ( $attr['only_last_page'] ? '{categories,feeds,news_' . $pages . '}' : '*' ) . '.xml', GLOB_BRACE  );
if( !empty( $xml_files ) ) {

    foreach( $xml_files as $xml_file ) {
        $file_info = pathinfo( $xml_file );
        $gz = gzopen( $attr['path'] . $file_info['basename'] . '.gz', 'w5' );
        gzwrite( $gz, file_get_contents( $xml_file ) );
        gzclose( $gz );
        unset( $gz );
        chmod( $attr['path'] . $file_info['basename'] . '.gz', 0777 );
    }
}

function build_sitemap_items( $type = 'section', $data, $filename ) { // type =
    global $allpages;

    if( empty( $data ) ) {
        return false;
    }

    switch( $type ) {
        case 'google':
            $url = $allpages->all( $data['category'] )->url();
            if( strpos( $url, 'http' ) === false ) {
                    $url = 'https://' . SUNSITE_DOMAIN . $url;
            }
            $titleGoogle = 'Украинский бизнес ресурс UBR.ua';

            $url = $url . ( !empty( $data['alias'] ) ? $data['alias'] . '-' . (empty($data['extcode']) ? $data['absnum'] : $data['extcode']) : $data['absnum'] );
            $gtext = '<url>'.'<loc>' . $url . '</loc>'.'<news:news>'.
            '<news:publication>'.
            '<news:name>'.$titleGoogle.'</news:name>'.
            '<news:language>ru</news:language>'.
            '</news:publication>'.
            '<news:publication_date>'.date( 'Y-m-d\TH:i:sP', $data['changed'] ).'</news:publication_date>'.
            '<news:title>'.$data['title'].'</news:title>'.
            '</news:news></url>';
            file_put_contents( $filename, $gtext, FILE_APPEND );
        break;
    }
}
