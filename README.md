# arche-biblatex

[![Build status](https://github.com/acdh-oeaw/arche-biblatex/actions/workflows/deploy.yaml/badge.svg)](https://github.com/acdh-oeaw/arche-biblatex/actions/workflows/deploy.yaml)
[![Coverage Status](https://coveralls.io/repos/github/acdh-oeaw/arche-biblatex/badge.svg?branch=master)](https://coveralls.io/github/acdh-oeaw/arche-biblatex?branch=master)

An ARCHE dissemination service providing mapping of resource's metadata to the [BibLaTeX](https://linorg.usp.br/CTAN/macros/latex/contrib/biblatex/doc/biblatex.pdf) and [CSL-JSON](https://citeproc-js.readthedocs.io/en/latest/csl-json/markup.html) bibliographic entries.

# Installation

* Run in the webserver docroot:
  ```bash
  composer require acdh-oeaw/arche-biblatex
  ln -s vendor/acdh-oeaw/arche-biblatex/index.php index.php
  cp vendor/acdh-oeaw/arche-biblatex/config-sample.yaml config.yaml
  ```
* Adjust `config.yaml`

# Usage

`http://deploymentUrl/?id=url-encoded-arche-resource-identifier`

Optional query parameters:

* `lang=two-letters-language-code` - preferred labels language, e.g. `lang=en`
* `override=csl-json-entry-or-biblatex-bibliographic-entry` - allows to manually override and/or add output fields. The format is autodetected and is independent of the output format (e.g. you can provided a CSL-JSON override while requesting the entry in the BibLaTeX format).
* `format=desired-reponse-format`
  * bibliographic data formats:
    * `application/vnd.citationstyles.csl+json` (default) or `application/json` - returning a [CSL-JSON](https://citeproc-js.readthedocs.io/en/latest/csl-json/markup.html),
    * `application/x-bibtex` - returning a [BibLaTeX bibliography entry](https://www.overleaf.com/learn/latex/Bibliography_management_with_biblatex#The_bibliography_file)
      ([reference documentation](https://mirrors.ibiblio.org/CTAN/macros/latex/contrib/biblatex/doc/biblatex.pdf)),
  * citation formats - name of any file (without the `.csl extension`) in the [official CSL styles repository](https://github.com/citation-style-language/styles), e.g. `apa-6th-edition`
* `noCache` - presence of this parameter overrides the cache. Useful for testing. Parameter value doesn't matter. Can be also empty.

# Override rules

* The base entry is created in the CSL according to the mapping provided in the `config.yaml`.
  The mapping is chosen based on the ARCHE resource RDF class.
* Then overrides comming from the ARCHE resource metadata property specified by the `config.yaml->biblatex->overrideProperty` configuration option are applied.
  If the override is provided in the BibLaTeX format, it is first converted to the CSL according to the `config.yaml->biblatex->biblatexToCsl` mapping rules.
* Finally overrides comming from the `override` request parameter are applied.
  If the override is provided in the BibLaTeX format, it is first converted to the CSL according to the `config.yaml->biblatex->biblatexToCsl` mapping rules.

## Details

* BibLaTeX output is generated only as a conversion of the native CSL-JSON output to the BibLaTeX using the `config.yaml->biblatex->biblatexToCsl` mapping rules.
* Overrides in the BibLaTeX format are fist converted to the CSL-JSON and then applied on the native CSL-JSON output.
  If you request output in the BibLaTeX format, then the result is again converted to the BibLaTeX.
  Because the CSL-JSON and BibLaTeX data models do not match perfectly, using BibLaTeX overrides can sometimes lead to the outputs you do not expect.
  It is safer to use overrides in the CSL-JSON as in that case not mapping has to be performed.
* If override is provided in the BibLaTeX format, then all bibliographic entry information including entry type and citation key are overriden, e.g.
  ```json
  {"type": "my type", "date": {"raw": "2025-06-03"}, "container-title": "Title of my choice"}
  ```
  Overrides can be provided both in the CSL-JSON and BibLaTeX formats.  
  If you want to provide override values in the BibLaTeX format but preserve the entry type and/or citation key use the following syntax:
    * either use the "magic" `NOOVERRIDE` type/citation key value, e.g.
      ```
      @NOOVERRIDE{NOOVERRIDE,
        fieldToOverride = {overriding value},
      }
      ```
    * or provide just BibLaTeX fields skipping the bibliographic entry header (and the final curly bracket), e.g.
      ```
      fieldToOverride1 = {overriding value},
      fieldToOverride2 = {overriding value}
      ```
* If you want a field to be skipped from the output, override it with an empty value.

## Example

Compare the output for the sample resource (https://hdl.handle.net/21.11115/0000-000E-753C-C).  
(please note the same data which are provided in the `override` request parameter below can be also provided in the `acdh:hasCustomCitation` resource metadata property)

* Default: https://arche-biblatex.acdh.oeaw.ac.at/?lang=en&id=https%3A%2F%2Fhdl.handle.net%2F21.11115%2F0000-000E-753C-C
* With entry type set to `book` `author` field overrided with a new value and `container-title` field removed:
  https://arche-biblatex.acdh.oeaw.ac.at/?lang=en&id=https%3A%2F%2Fhdl.handle.net%2F21.11115%2F0000-000E-753C-C&override=%7B%22type%22%3A%22book%22%2C%22author%22%3A%5B%7B%22family%22%3A%22Doe%22%2C%22give%22%3A%22John%22%7D%5D%2C%22container-title%22%3A%22%22%7D
  The non-URL-encoded `override` parameter value () used here is:
  ```
  {
    "type": "book",
    "author": [{"family": "Doe", "give": "John"}],
    "container-title": "",
  }
  ```
* An override in a BibLaTeX format with `author` field overrided with a new value, the `booktitle` field removed (which corresponds to the `container-title` in the CSL-JSON)
  and the result required to be a BibLaTeX bibliographic entry.
  As the short BibLaTeX override syntax is used (no type and citation key declaration), an automatically created BibLaTeX entry type and citation key are kept.
  https://arche-biblatex.acdh.oeaw.ac.at/?lang=en&id=https%3A%2F%2Fhdl.handle.net%2F21.11115%2F0000-000E-753C-C&override=author%20%3D%20%7BDoe%2C%20John%7D%0A%2Cbooktitle%20%3D%20%7B%7D%2Cbookauthor%20%3D%20%7B%7D%2C&format=application%2Fx-bibtex
  The non-URL-encoded `override` parameter value () used here is:
  ```
  author = {Doe, John},  
  bookauthor = {},
  ```

# Docker deployment

The `build/docker` directory contains a Dockerfile defining a runtime environment for the service.

It takes one build argument `VARIANT` which can be either `production` or `development` and affects the PHP ini settings - see the Configuration section of the https://hub.docker.com/_/php.

It expects all the service files (including composer libraries and the desired `config.yaml`) to be provided in `build/docroot` during the build time.

For a live development you can just build it with an empty `build/docroot` directory and mount service files during `docker run`.

Examples:

* Creating a production image - see the https://github.com/acdh-oeaw/arche-biblatex/blob/master/.github/workflows/deploy.yaml
* Creating a development image and running it locally
  ```bash
  # install dependencies
  composer update
  # prepare an empty docroot
  mkdir build/docroot
  # build the image
  docker build --rm -t acdhch/arche-biblatex:dev --build-arg VARIANT=development build
  # run the image using current directory as a docroot making it available on local port 8080
  docker run -d --name arche-biblatex -p 8080:80 -v `pwd`:/var/www/html acdhch/arche-biblatex:dev
  # check if it works
  curl -i 'http://127.0.0.1:8080/?id=https%3A%2F%2Fid.acdh.oeaw.ac.at%2Fgtrans'
  ```
