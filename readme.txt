=== BasePress Knowledge Base + SearchWP Integration ===
Contributors: codesavory
Donate link: https://codesavory.com
Tags: knowledge base, help desk,documentation,faq, searchwp, search
Requires at least: 4.5
Tested up to: 5.7
Stable tag: 1.1.3
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrate BasePress Knowledge Base Premium with SearchWP 3.x and 4.x

== Description ==

= [SearchWP 4.0 is coming soon!](https://searchwp.com/news/searchwp-4-0-is-coming/) We have already included support for this major release! 👍 =

BasePress is the best option when it comes to building single or multiple knowledge bases on your WordPress site.
If you want to use SearchWP to power your BasePress searches this add-on is what you are looking for!
For this add-on to work you need a copy of [SearchWP](https://searchwp.com/) and [BasePress Premium](https://codesavory.com/) 2.6.8 or above.


= Why is this plugin necessary? =
BasePress was developed with a search feature of its own which filters the results according to the Knowledge Base visited, by language when used wth WPML and by BasePress content restrictions.
This add-on makes it possible to use SearchWP to power the knowledge base searches and retain the automatic filtering of the content.
It also integrates SearchWP with BasePress Live results.


== How to use ==

1. Install this add-on along with BasePress Premium and SearchWP
2. Visit SearchWP Settings page and create a new search engine.
3. Rename the new search engine to "Knowledge Base".
4. Add the "Knowledge Base" post type to the engine.
5. Set up your engine as needed.
6. Save SearchWP engine settings.

Once SearchWP index is fully built, searches in your knowledge base will be run by SearchWP.

== Frequently Asked Questions ==

= Does this work with all BasePress versions? =
This plugin requires BasePress Premium from version 2.6.7 or later.

= Can I use SearchWP with BasePress free edition? =
No. You need a Premium version of BasePress for the integration to fully work with SearchWP.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/basepress-searchwp-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. This plugin has no settings


== Changelog ==

= 1.1.3 =
* Set proper table alias when content restriction is enabled and SearchWP 4 is used

= 1.1.2 =
* Updated integration with SearchWP 4.x. It requires SearchWP from 4.0.19

= 1.1.1 =
* Updated integration with SearchWP 4.x to prevent PHP error on activation

= 1.1.0 =
* Added support for SearchWP 4.x
* Fixed hardcoded table prefix in query

= 1.0.0 =
* First release
