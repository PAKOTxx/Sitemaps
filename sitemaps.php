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

# News sitemaps
$limit = 30000;

$pages = $db->qrow( "SELECT COUNT( * ) AS cnt FROM articles AS a WHERE approved = 1 AND EXISTS( SELECT absnum FROM pages WHERE absnum = a.category AND type & {t|t} > 0 )", array(
    't' => $phrase->bit( 'sunsite/pages_props/news' )
) );
$pages = ceil( $pages['cnt']/$limit );
for( $page = $attr['only_last_page'] ? $pages : 1; $page <= $pages; $page++ ) {
    $filename = 'news_' . $page;

    $query = $db->exquery( "SELECT absnum, alias, category, changed, extcode FROM articles AS a WHERE approved = 1 AND langid={l|i} AND EXISTS( SELECT absnum FROM pages WHERE absnum = a.category AND approved = 1 AND type & {t|t} > 0 ) ORDER BY adate ASC LIMIT {s|t}, {e|t}", array(
        'l' => $attr['langid'],
        't' => $phrase->bit( 'sunsite/pages_props/news' ),
        's' => ($page-1)*$limit,
        'e' => $limit
    ) );

    file_put_contents( $attr['tmp_path'] . $filename . '.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' );

    while( $row = $query->nextAssoc() ) {
        build_sitemap_items( 'article', $row, $attr['tmp_path'] . $filename . '.xml' );
    }

    file_put_contents( $attr['tmp_path'] . $filename . '.xml', '</urlset>', FILE_APPEND );
    echo ' > ' . $attr['tmp_path'] . $filename . '.xml - ready' . PHP_EOL;

    unset( $query );
}


# Categories sitemap
$filename = 'categories';
$query = $db->exquery( "SELECT absnum FROM pages WHERE numsup = 0 AND approved = 1 AND type & {t|t} > 0", array(
    't' => $phrase->bit( 'sunsite/pages_props/news' )
) );

file_put_contents( $attr['tmp_path'] . $filename . '.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' );
build_sitemap_items( 'section', $allpages->all( 1000 ), $attr['tmp_path'] . $filename . '.xml' );
while( $row = $query->nextAssoc() ) {
    build_sitemap_items( 'section', $allpages->all( $row['absnum'] ), $attr['tmp_path'] . $filename . '.xml' );
}
file_put_contents( $attr['tmp_path'] . $filename . '.xml', '</urlset>', FILE_APPEND );
echo ' > ' . $attr['tmp_path'] . $filename . '.xml - ready' . PHP_EOL;

unset( $query );


# Feed sitemap
$filename = 'feeds';
$query = $db->exquery( "SELECT absnum, alias, category FROM articles AS a WHERE approved = 1 AND langid = {l|i} AND category = {c|i} ORDER BY position DESC", array(
    'l' => $attr['langid'],
    'c' => 2598
) );
file_put_contents( $attr['tmp_path'] . $filename . '.xml', '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' );
while( $row = $query->nextAssoc() ) {
    build_sitemap_items( 'article', $row, $attr['tmp_path'] . $filename . '.xml' );
}
file_put_contents( $attr['tmp_path'] . $filename . '.xml', '</urlset>', FILE_APPEND );
echo ' > ' . $attr['tmp_path'] . $filename . '.xml - ready' . PHP_EOL;


# Index for sitemaps
// clear old files
$old_files = glob( $attr['path'] . ( $attr['only_last_page'] ? '{categories,feeds}' : '*' ) . '.xml.gz', GLOB_BRACE );
if( !empty( $old_files ) ) {
    foreach( $old_files as $old_file ) {
        @unlink( $old_file );
    }
}
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
// create sitemap index
$gz_files = glob( $attr['path'] . '*.gz' );
if( !empty( $gz_files ) ) {
    $filename = 'sitemap';

    file_put_contents( $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml', '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' );
    foreach( $gz_files as $gz_file ) {
        file_put_contents( $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml', '<sitemap><loc>' . str_replace( $_SERVER['DOCUMENT_ROOT'], 'https://' . SUNSITE_DOMAIN, $gz_file )
                . '</loc><lastmod>' . date( 'Y-m-d\\TH:i:sP', filemtime( $gz_file ) ) . '</lastmod></sitemap>', FILE_APPEND );
    }
    file_put_contents( $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml', '</sitemapindex>', FILE_APPEND );
    // pack
    $gz = gzopen( $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml.gz', 'w5' );
    gzwrite( $gz, file_get_contents( $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml' ) );
    gzclose( $gz );
    unset( $gz );
    chmod( $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml', 0777 );
    chmod( $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml.gz', 0777 );
    echo ' > ' . $_SERVER['DOCUMENT_ROOT'] . '/' . $filename . '.xml.gz' . ' - ready' . PHP_EOL;
}


function build_sitemap_items( $type = 'section', $data, $filename ) { // type = sercion | article
    global $allpages, $phrase, $attr;

    if( empty( $data ) ) {
        return false;
    }

    switch( $type ) {
        case 'section':
            if( $data->absnum == 1000 ) {
                $priority = '1.0';
            } else {
                $priority = '0.8';
            }

            if ( (int)( $data->type & $phrase->bit( 'sunsite/pages_props/hidden' ) ) > 0 ) {
                return false;
            }

            $url = substr( $data->url(), 0, -1 );
            if( $url == '/index' ) {
                $url = '';
            }
            if( strpos( $url, 'http://' ) === false ) {
                $url = 'https://' . SUNSITE_DOMAIN . $url;
            }

            file_put_contents( $filename, '<url><loc>' . $url . '</loc><changefreq>hourly</changefreq><priority>' . $priority . '</priority></url>', FILE_APPEND );

            if( count( $data->childs() ) > 0 ) {
                foreach( $data->childs() as $ch ) {
                    build_sitemap_items( 'section', $ch, $filename );
                }
            }
        break;
        case 'article':
            $priority = '0.5';
            $img = '';
            if( file_exists( $_SERVER['DOCUMENT_ROOT'] . '/img/article/' . intval( $data['absnum']/100 ) . '/' . intval( $data['absnum']%100 ) . '_main.jpg' ) ) {
                $img = '<image:image><image:loc>http://' . SUNSITE_DOMAIN . '/img/article/' . intval( $data['absnum']/100 ) . '/' . intval( $data['absnum']%100 ) . '_main.jpg</image:loc></image:image>';
            }

            $url = $allpages->all( $data['category'] )->url();
            if( strpos( $url, 'http://' ) === false ) {
                $url = 'https://' . SUNSITE_DOMAIN . $url;
            }
            $url = $url . ( !empty( $data['alias'] ) ? $data['alias'] . '-' . (empty($data['extcode']) ? $data['absnum'] : $data['extcode']) : $data['absnum'] );
            file_put_contents( $filename,
                    '<url><loc>' . $url . '</loc>' .
                    ( !empty( $data['changed'] ) ? '<lastmod>' .date( 'Y-m-d\TH:i:sP', $data['changed'] ). '</lastmod>' : '' ) .
                    '<priority>' . $priority . '</priority>' . $img . '</url>', FILE_APPEND );
        break;
    }
}
