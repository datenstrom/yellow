# Datenstrom Yellow tasklist

You can help us with open tasks for Datenstrom Yellow:

- [ ] Added support for installing extensions in web browser. Users want to install extensions in browser.
- [ ] Added support for light and dark mode to all themes. Light and dark mode is expected on mobile devices.
- [ ] Added support for page history in wiki extension. Users want to see/compare what has changed.
- [ ] Added support for search in static website. Give users similar features in dynamic/static website.
- [ ] Added support for dynamic loading of JS/CSS files in bundler. Better page loading time.
- [ ] Added support for web forms in Markdown. Users can create email contact forms or a feedback/survey forms.
- [ ] Added support for Wysiwyg editor for Markdown. Users can edit websites without much knowledge.
- [ ] Updated API, YellowPageCollection no longer derives from ArrayObject. ArrayObject interface is strange.
- [ ] Updated API, changed getAvailable() to enumerate(). Designers want to use non-flattened themes.
- [x] Updated API, changed content element type notice to general. Make it more intuitive.
- [x] Updated core extension, support for webmanifest files was added. Websites and web applications use it.
- [ ] Updated contact extension, message delivery with brute force protection. Spammers gonna spam.
- [ ] Updated edit extension, autocomplete for links and tags. Users do less, software does more.
- [ ] Updated edit extension, settings dialog with dropdown menus. Users want important system settings in browser.
- [x] Updated edit extension, upload with different JPEG file name extensions. Mobile devices use different formats.
- [ ] Updated edit extension toolbar, improved emoji and icon selection dialog. Give users more control.
- [ ] Updated edit extension toolbar, improved link and file selection dialog. Give users more control.
- [ ] Updated edit extension toolbar, menu for buttons on small screens. Disappearing buttons.
- [x] Updated gallery extension, popup can be triggered by clicking on a link. Give users more flexibility.
- [ ] Updated icon extension, SVG stack instead of WOFF font. Developers want consistent files formats.
- [ ] Updated image extension, different media files for light and dark mode. Give users more control.
- [x] Updated Markdown extension, improved email handling for long TLD. TLD with more than 3 characters.
- [x] Updated Markdown extension, syntax for block elements has changed. Make it more intuitive.
- [ ] Updated feed extension, short URL for the feed.xml. Users don't like the long URL, it's ugly. 
- [ ] Updated sitemap extension, short URL for the sitemap.xml. Users don't like the long URL, it's ugly.
- [x] Updated themes, CSS for coloured block elements has changed. Make it more intuitive.
- [x] Updated website, more information about latest product changes. People want more information.
- [ ] Updated website, Swedish translation for missing help pages. Better multi language documentation.
- [ ] Published comment extension, no longer experimental. Due to public demand.
- [ ] Published math extension, no longer experimental. Due to scientific demand.
- [ ] Published maintenance extension, no longer experimental. Due to practical demand.
- [ ] Published SMTP extension, send emails to remote server. Websites may not have a working mail system.
- [ ] Tested performance with thousands of content files. For people who make large websites.

## How to improve code

You can find core functionality in the [core](https://github.com/annaesvensson/yellow-core) and everything else in [extensions](https://datenstrom.se/yellow/extensions/). Good technology is made for people. Imagine what the user wants to do and what would make their life easier. Remember to focus on people. Not on technical details and lots of features. Did you improve code? The first option is to send a pull request to the developer, it may or may not be accepted. The second option is to discuss your changes with the Datenstrom community. The third option is to make a new extension with the modified code.

## How to improve documentation

You can find basic documentation in the [help](https://github.com/annaesvensson/yellow-help) and more detailed documentation in [extensions](https://datenstrom.se/yellow/extensions/). Typically the documentation of an extension consists of multiple sections, with examples to copy/paste and settings you can customise. Review the entire documentation from the perspective of the user. Imagine what the user wants to do and what would make their life easier. Did you improve documentation? Fork the relevant repository. Upload your changes and send a pull request to the developer.

Do you have questions? [Get help](https://datenstrom.se/yellow/help/).
