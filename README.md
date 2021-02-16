# arche-bibtex

An ARCHE dissemination service providing mapping of resource's metadata to BibTeX bibliographic entries.

# Installation

* Run in the webserver docroot:
  ```bash
  composer require acdh-oeaw/arche-bibtex
  ln -s vendor/acdh-oeaw/arche-bibtex/index.php index.php
  cp vendor/acdh-oeaw/arche-bibtex/config-sample.yaml config.yaml
  ```
* Adjust `config.yaml`

# Usage

`http://deploymentUrl/?id=url-encoded-arche-resource-identifier&lang=preferredLanguageCode`
