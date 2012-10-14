<?php
/*
Plugin Name: Markup to Featured Image
Plugin URI: http://wordpress.org/extend/plugins/markup-to-featured-image/
Description: Automatically set the featured image for a post based on custom markup in the body text. 
Version: 1.0
Author: Ian Beck
Author URI: http://beckism.com/

Many thanks to Aditya Mooley for his Auto Post Thumbnail plugin; most of the code here is based on his work.
*/

/*  Copyright 2012  Ian Beck

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Add our hooks into the publishing system
add_action('publish_post', 'm2fi_publish_post');
// This hook handles scheduled posts and other publishing tyes that do not trigger 'publish_post'
add_action('transition_post_status', 'm2fi_post_status_change');
// And our filter to prevent the featured-image markup from being output to the blog
add_filter('the_content', 'm2fi_filter_markup_from_content');

/**
 * Function to check whether scheduled post is being published
 *
 * @param $new_status
 * @param $old_status
 * @param $post
 * @return void
 */
function m2fi_post_status_change($new_status='', $old_status='', $post='') {
	global $post_ID; // Using the post id from global reference since it is not available in $post object. Strange!

	if ('publish' == $new_status) {
		m2fi_publish_post($post_ID);
	}
}

/**
 * Function to convert the following markup into a featured image:
 * 
 *     <img src="/path/to/media/image.jpg" data-featured-image="keep" />
 *     <img src="/path/to/media/image.jpg" data-featured-image="strip" />
 *     <!--featured-image:/path/to/media/image/.jpg-->
 * 
 * The first example will populate the featured image, but leave the <img>
 * element in the post body. The second will strip the <img> element out of
 * the post's body text when displaying it on the site. The third, being a comment,
 * will never be displayed at all.
 */
function m2fi_publish_post($post_id) {
	global $wpdb;

	// First check whether Post Thumbnail is already set for this post.
	if (get_post_meta($post_id, '_thumbnail_id', true)) {
		return;
	}

	$post = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE id = $post_id");

	// Initialize variable used to store list of matched images as per provided regular expression
	$matches = array();
	$comment_format = false;

	// Look for an image with the appropriate markup
	preg_match('/<\s*img [^>]*data-featured-image\s*=\s*("|\')(keep|strip)\1[^>]*>/i', $post[0]->post_content, $matches);
	if (!count($matches)) {
		$comment_format = true;
		preg_match('/<!--\s*featured-image:\s*(.+?)\s*-->/', $post[0]->post_content, $matches);
	}

	if (count($matches)) {
		if ($comment_format) {
			$url = $matches[1];
			$thumb_id = '';
		} else {
			$url = preg_replace('/^.*?\s+src\s*=\s*(["\'])(.+?)\1.*$/i', '$2', $matches[0]);
			if ($url == $matches[0]) {
				// No src element!
				return;
			}
			// If the image is from the media gallery, the thumb ID will be included in the class block
			preg_match('/\s+class\s*=\s*["\'][^"\']*?wp-image-([\d]*)/i', $image, $thumb_id);
			$thumb_id = $thumb_id[1];
		}

		// If we don't have a thumb ID, check for the image in the DB. Thanks to "Erwin Vrolijk" for providing this code.
		if (!$thumb_id) {
			$result = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE guid = '".$url."'");
			$thumb_id = $result[0]->ID;
		}

		// Still no ID, which means we need to generate a new media image and use that
		if (!$thumb_id) {
			// If we have an IMG tag, see if we can extract a title
			$image_title = '';
			if (!$comment_format) {
				$image_title = preg_replace('/^.*?\s+title\s*=\s*(["\'])(.+?)\1.*$/i', '$2', $matches[0]);
				if ($image_title == $matches[0]) {
					// No title
					$image_title = '';
				}
			}
			$thumb_id = m2fi_generate_post_thumb($url, $image_title, $post[0]->post_content, $post_id);
		}

		// If we get here and have a thumb_id, update the post!
		if ($thumb_id) {
			update_post_meta($post_id, '_thumbnail_id', $thumb_id);
			break;
		}
	}
}

/**
 * Function to fetch the image from URL and generate the required thumbnails
 */
function m2fi_generate_post_thumb($image_url, $image_title, $post_content, $post_id) {
	// Get the file name
	$filename = substr($image_url, (strrpos($image_url, '/'))+1);

	if (!(($uploads = wp_upload_dir(current_time('mysql')) ) && false === $uploads['error'])) {
		return null;
	}

	// Generate unique file name
	$filename = wp_unique_filename($uploads['path'], $filename);

	// Move the file to the uploads dir
	$new_file = $uploads['path'] . "/$filename";

	if (!ini_get('allow_url_fopen')) {
		$file_data = m2fi_curl_get_file_contents($image_url);
	} else {
		$file_data = @file_get_contents($image_url);
	}

	if (!$file_data) {
		return null;
	}

	file_put_contents($new_file, $file_data);

	// Set correct file permissions
	$stat = stat(dirname($new_file));
	$perms = $stat['mode'] & 0000666;
	@chmod($new_file, $perms);

	// Get the file type. Must to use it as a post thumbnail.
	$wp_filetype = wp_check_filetype($filename, $mimes);

	extract($wp_filetype);

	// No file type! No point to proceed further
	if ((!$type || !$ext) && !current_user_can('unfiltered_upload')) {
		return null;
	}

	// Compute the URL
	$url = $uploads['url'] . "/$filename";
	// If we don't have an image title, default to the filename minus the extension
	if (!$image_title) {
		$image_title = substr($filename, 0, strrchr($filename, '.'));
	}

	// Construct the attachment array
	$attachment = array(
		'post_mime_type' => $type,
		'guid' => $url,
		'post_parent' => null,
		'post_title' => $image_title,
		'post_content' => '',
	);

	$thumb_id = wp_insert_attachment($attachment, $file, $post_id);
	if (!is_wp_error($thumb_id)) {
		require_once(ABSPATH . '/wp-admin/includes/image.php');

		// Added fix by misthero as suggested
		wp_update_attachment_metadata($thumb_id, wp_generate_attachment_metadata($thumb_id, $new_file));
		update_attached_file($thumb_id, $new_file);

		return $thumb_id;
	}

	return null;
}

/**
 * Function to fetch the contents of URL using curl in absense of allow_url_fopen.
 *
 * Copied from user comment on php.net (http://in.php.net/manual/en/function.file-get-contents.php#82255)
 */
function m2fi_curl_get_file_contents($URL) {
    $c = curl_init();
    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($c, CURLOPT_URL, $URL);
    $contents = curl_exec($c);
    curl_close($c);

    if ($contents) {
        return $contents;
    }

    return FALSE;
}

/**
 * Filters out featured image markup from the content when output to the site
 */
function m2fi_filter_markup_from_content($content) {
	$content = preg_replace('/<\s*img [^>]*data-featured-image\s*=\s*("|\')strip\1[^>]*>|<!--\s*featured-image:.+?\s*-->|data-featured-image\s*=\s*("|\')keep\2/i', '', $content);
	return $content;
}
