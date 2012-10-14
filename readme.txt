=== Markup to Featured Image ===
Contributors: beckism
Tags: Post, thumbnail, posts, featured image, image, featured, images
Requires at least: 2.9.1
Tested up to: 3.4.2
Stable tag: 1.0.0

Automatically set the featured image (formerly the post thumbnail) for a post based on custom markup in the body text.

== Description ==

Markup to Featured Image will automatically generate a featured image when you publish a post, with the intent of allowing you to publish posts with featured images from third-party clients like MarsEdit.

To add a featured image to your post, include one of the following markup variants in your post's content:

    <img src="/path/to/image.jpg" data-featured-image="keep" />
    <img src="/path/to/image.jpg" data-featured-image="strip" />
    <!--featured-image:/path/to/image/.jpg-->

If you use an `<img>` element, the values of the `data-featured-image` attribute specify whether the image should be displayed when the post is shown on your site (`keep`) or ignored when the post is displayed (`strip`). The order of attributes in your `<img>` tag (and whether you include other attributes, like width/height, title, alt, etc.) doesn't matter. You can make it as complicated or simple as you like.

If the post already has a featured image, the plugin will do nothing (version 1.0 does *not* allow updating the thumbnail by updating the markup).

== Installation ==

1. Upload directory `markup-to-featured-image` to the `/wp-content/plugins/` directory
2. Activate the plugin through the Plugins menu in WordPress
3. That's it! Enjoy being able to publish posts with featured images from anywhere

== Changelog ==

= 1.0.0 =
* First release; heavily based off of v3.3.0 of Adity Mooley's [Auto Post Thumbnail](http://wordpress.org/extend/plugins/auto-post-thumbnail/) plugin
