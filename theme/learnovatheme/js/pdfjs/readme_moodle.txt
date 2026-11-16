PDF.js (pdfjs-dist) — vendored for the focus-mode PDF viewer.

Version: 4.10.38 (legacy build)
Source:  https://www.npmjs.com/package/pdfjs-dist
Licence: Apache-2.0 (see LICENSE)

What is here and why
--------------------
pdf.min.mjs         Main library. The *legacy* build is used on purpose: the
                    modern one relies on Promise.withResolvers and friends,
                    which rules out browsers only a couple of years old.
pdf.worker.min.mjs  Parsing/rendering worker. Loaded by the library itself from
                    GlobalWorkerOptions.workerSrc, set in amd/src/focus_pdf.js.
standard_fonts/     Replacements for the 14 standard PDF fonts, fetched on
                    demand for documents that do not embed them. Without these
                    such documents render with the wrong metrics or not at all.

Deliberately not vendored: cmaps/ (~1.7 MB), needed only for CJK documents that
use predefined character maps. Add the directory here and point
`cMapUrl` at it in focus_pdf.js if those ever turn up.

Files are served straight off the theme directory as static assets, so .mjs has
to keep its extension — Apache maps it to text/javascript, which the module
loader requires. The library is loaded with a dynamic import() rather than
through RequireJS: these are ES modules, and Moodle's AMD loader cannot consume
them.

Upgrading
---------
    npm pack pdfjs-dist@<version>
    tar xzf pdfjs-dist-<version>.tgz
    cp package/legacy/build/pdf.min.mjs package/legacy/build/pdf.worker.min.mjs .
    cp package/LICENSE .
    rm -rf standard_fonts && cp -r package/standard_fonts .

Then update the version in this file and in thirdpartylibs.xml, and re-check
amd/src/focus_pdf.js against the API: the TextLayer class and the
--scale-factor CSS variable it depends on have both changed name between major
versions.
