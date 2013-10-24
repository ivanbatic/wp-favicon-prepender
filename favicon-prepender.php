<?php

/*
  Plugin Name: Favicon Prepender
  Plugin URI: http://www.github.com/ivanbatic/wp-favicon-prepender
  Description: This plugin prepends a site favicon to every external link found in posts.
  Version: 1.0
  Author: Ivan Batić
 */

add_filter('the_content', 'prepend_favicon', 20);

/**
 * Prepends a favicon in an image tag to each external link in the post content
 * @param string $post
 * @return string
 */
$favicon_prepender_cache = [];

function prepend_favicon($post) {
    /*
     * Gets all the <a... </a> tags in the text 
     * with a backreference to the whole tag
     * and to the url as well.
     * Url can be enclosed with quotes, double quotes and not enclosed at all,
     * since it's valid with HTML5.
     * Links containing `http` are considered external.
     */
    $pattern = '/<a.*href=((?<=href=).?http.*?(?=[>])).*<\/a>/i';

    // Parses the post, prepending a favicon image
    $post = preg_replace_callback($pattern, function($backref) {
            $favicon = get_favicon_url(trim($backref[1], '"\''));
            return "<img src='{$favicon}'/>{$backref[0]}";
        }, $post);
    return $post;
}

/**
 * Returns the favicon image source for a given url
 * @param string $url
 * @param string $method Can be `simple`, `google` or `complex`. <br/>
 * Simple method just adds /favicon.ico to the domain.<br/>
 * Google method is optimal, it uses Google's favicon service which returns the real favicon from their cache.<br/>
 * Complex method is very slow, as it fetches the whole page in order to 
 * check for a link icon tag in head if it's not found using the simple method.
 * @return string
 */
function get_favicon_url($url, $method = 'simple') {
    $parsed = parse_url($url);
    $host = $parsed['host'] ? : $parsed['path'];
    $scheme = $parsed['scheme'] ? : 'http';
    switch ($method) {
        case 'simple':
            return "{$scheme}://{$host}/favicon.ico";
        case 'complex':

            // If we already found the icon, that's great!
            global $favicon_prepender_cache;
            if ($favicon_prepender_cache[$url]) {
                return $favicon_prepender_cache[$url];
            }
            // Usual names for a favicon
            $list = array(
                'favicon.ico',
                'favicon.png'
            );

            // Check if one of these exists
            foreach ($list as $icon) {
                $maybe = rtrim($url, '/') . '/' . $icon;
                $check = wp_remote_head($maybe);
                if ($check['response']['code'] == 200 && strpos($check['headers']['content-type'], 'image') !== false) {
                    $favicon_prepender_cache[$url] = $maybe;
                    return $maybe;
                }
            }
            // Guess not, this is bad...
            $doc = new \DOMDocument();
            $doc->strictErrorChecking = false;
            try {
                $content = wp_remote_get($url);
                libxml_use_internal_errors(true);
                $doc->loadHTML($content['body']);
                libxml_use_internal_errors(false);
                $xml = simplexml_import_dom($doc);
                $arr = $xml->xpath('//link[@rel="shortcut icon"]');
            } catch (Exception $ex) {
                // Nah...
            }
            $favicon_prepender_cache[$url] = $arr[0]['href'];
            return $arr[0]['href'];
        case 'google':
        default:
            return 'http://www.google.com/s2/favicons?domain=' . $url;
    }
}