=== TechPlug GH Blog and Archive ===
Author: TechPlug GH
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A professional, native-looking blog and article archive for the TechPlug GH storefront.

== What it does ==
* Creates a "Guides and Reviews" hub page at /blog/ on activation.
* Adds a Blog link to the primary menu (desktop and mobile). If the theme is
  using its built-in fallback menu, it creates a matching menu and adds the link,
  so it just works after activation.
* Lists all published posts in a responsive card grid styled to match the Aurora
  theme (reuses the theme component classes plus a small scoped stylesheet built
  from the theme colour tokens).
* Features the latest article, a topic search, filter pills by topic, reading
  time, category badges, and pagination.
* Scheduled posts appear automatically as they go live.

== Install ==
1. Plugins > Add New > Upload Plugin > choose tpg-blog.zip > Install > Activate.
2. Visit /blog/ (link is in the menu). Done.

== Notes ==
* No theme files are modified. Deactivating removes the dynamic menu link; the
  hub page and any published posts are preserved.
* Defaults (page title, menu label, posts per page, intro text) are filterable
  for developers via the tpgb_* filters in the main plugin file.

== Changelog ==
= 1.0.0 =
* Initial release.
