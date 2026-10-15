Self-hosted fonts for the "original" look (css/looks/original.css).

Playfair Display, Lato and Outfit, all under the SIL Open Font License 1.1
(the licence text for each is in OFL-<family>.txt here). Latin subset, woff2,
only the weights the first sandbox build used. Taken from the @fontsource
packages (version 5.3.0), which repackage the Google Fonts releases, so the
site makes no request to Google or any other font service.

  Playfair Display  700, 900, italic 400, italic 700
  Lato              300, 400, 700
  Outfit            300, 400, 600, 700

They are only requested while the "original" look is active (the @font-face
rules live in that look's CSS file), and only if text actually uses them.
