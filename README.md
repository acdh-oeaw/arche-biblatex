# arche-bibtex

An ARCHE dissemination service providing mapping of resource's metadata to BibLaTeX bibliographic entries.

# Installation

* Run in the webserver docroot:
  ```bash
  composer require acdh-oeaw/arche-biblatex
  ln -s vendor/acdh-oeaw/arche-biblatex/index.php index.php
  cp vendor/acdh-oeaw/arche-biblatex/config-sample.yaml config.yaml
  ```
* Adjust `config.yaml`

# Usage

`http://deploymentUrl/?id=url-encoded-arche-resource-identifier&lang=preferredLanguageCode`
