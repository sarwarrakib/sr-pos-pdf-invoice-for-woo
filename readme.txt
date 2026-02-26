=== SR POS - PDF Invoice & Packing Slip for WooCommerce ===
Contributors: sarwarrakib
Tags: woocommerce, pdf invoice, packing slip, pos, watermark
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.7.24
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create invoices and packing slips for WooCommerce orders. Includes PDF preview, direct download, print view, and watermark support.

== Description ==

SR POS - PDF Invoice & Packing Slip for WooCommerce adds:

* Invoice & Packing Slip templates
* Direct PDF Download / PDF View (mPDF)
* HTML Print View (browser print)
* Watermark support
* Unicode text support for mixed languages

== Installation ==

1. Upload the plugin zip via **Plugins -> Add New -> Upload Plugin**.
2. Activate the plugin.
3. Configure settings from the plugin menu.

== Frequently Asked Questions ==

= Does it support Bengali and English together? =
Yes. The templates are Unicode-safe and include font fallbacks for mixed Bengali/English text.

== Changelog ==

= 1.1.7.24 =
* Fix: Set distinct Plugin URI for WordPress.org compliance (plugin page URL differs from author URL).

= 1.1.7.22 =
* Fix: Restore Bengali + Taka (৳) rendering in PDF by aliasing mPDF's Bengali language font (freeserif) to the bundled Noto Sans Bengali.

= 1.1.7.21 =
* Fix: Force Bengali runs and currency symbol to use Bengali font in PDF output (prevents tofu boxes after slimming font bundle).

= 1.1.7.20 =
* Fix: HTML Print View watermark opacity and embedded Bengali font loading (CSS placeholders now render correctly).

= 1.1.7.18 =
* Fix: readme (English-only short description + name match) and remove Plugin Check DB meta_query warnings.

= 1.1.7.16 =
* Fix: prevent fatal error on activation by loading the plugin autoloader and core class definitions correctly.

= 1.1.7.11 =
* Fix: ensure a visible space after Name/Phone/Email/Address labels in PDF View and Direct Download.

= 1.1.7.8 =
* Branding update (SarwarRakib).
* Minor metadata cleanup for WordPress.org submission.

== Upgrade Notice ==

= 1.1.7.18 =
Recommended update.

= 1.1.7.16 =
Important hotfix.

= 1.1.7.11 =
Recommended update.

= 1.1.7.8 =
Recommended update.

== Third-Party Assets ==

* mPDF library (GPLv2): bundled under /vendor/mpdf.
* Noto Sans Bengali fonts (SIL Open Font License 1.1): bundled under /assets/fonts.