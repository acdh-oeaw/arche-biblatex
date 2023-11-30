# arche-biblatex

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

Optionally an `override` query parameter can be also supplied providing URL-encoded BibLaTeX bibliographic entry or its fragment.
It works in the same way as an override coming from ARCHE resource metadata (details below), just is applied after it.

# Override rules

* The base BibLaTeX bibliographic entry is created according to the mapping provided in the `config.yaml`.
  The mapping is chosen based on the ARCHE resource RDF class.
* Then overrides comming from the ARCHE resource metadata property specified by the `config.yaml->biblatex->biblatexProperty` configuration option are applied.
* Finally overrides comming from the `override` request parameter are applied.


## Details

* All bibliographic entry information including entry type and citation key are overrided.
  If you want to preserve the entry type and/or citation key:
    * either use the "magic" `NOOVERRIDE` type/citation key value, e.g.
      ```
      @NOOVERRIDE{NOOVERRIDE,
        fieldToOverride = {overriding value},
      }
      ```
    * or provide just BibLaTeX fields skipping the bibliographic entry header (and the final curly bracket), e.g.
      ```
      fieldToOverride = {overriding value},
      ```
* If you want a field to be skipped from the output, override it with an empty value.

## Example

Compare the output for the sample resource (https://hdl.handle.net/21.11115/0000-000E-753C-C).  
(please note the same data which are provided in the `override` request parameter below can be also provided in the "custom citation" resource metadata property)

* Default: https://arche-biblatex.acdh.oeaw.ac.at/?lang=en&id=https%3A%2F%2Fhdl.handle.net%2F21.11115%2F0000-000E-753C-C`
* With entry type set to `book` but citation key preserved, `author` field overrided with a new value as well as `booktitle` and `bookauthor` fields removed:
  https://arche-biblatex.acdh.oeaw.ac.at/?lang=en&id=https%3A%2F%2Fhdl.handle.net%2F21.11115%2F0000-000E-753C-C&override=%40book%7BNOOVERRIDE%2C%0A%20%20author%20%3D%20%7BDoe%2C%20John%7D%0A%2C%20%20booktitle%20%3D%20%7B%7D%2C%20bookauthor%20%3D%20%7B%7D%2C%0A%7D  
  The non-URL-encoded `override` parameter value () used here is:
  ```
  @book{NOOVERRIDE,
    author = {Doe, John},
    booktitle = {},
    bookauthor = {},
  }
  ```
* With `author` field overrided with a new value as well as `booktitle` and `bookauthor` fields removed.
  As automatically created entry type and citation key are to be kept, a short syntax skipping the BibLaTeX entry header is used.
  https://arche-biblatex.acdh.oeaw.ac.at/?lang=en&id=https%3A%2F%2Fhdl.handle.net%2F21.11115%2F0000-000E-753C-C&override=author%20%3D%20%7BDoe%2C%20John%7D%0A%2Cbooktitle%20%3D%20%7B%7D%2Cbookauthor%20%3D%20%7B%7D%2C
  The non-URL-encoded `override` parameter value () used here is:
  ```
  author = {Doe, John},  
  booktitle = {}, 
  bookauthor = {},
  ```

# Docker deployment

The `build/docker` directory contains a Dockerfile defining a runtime environment for the service.

It takes one build argument `VARIANT` which can be either `production` or `development` and affects the PHP ini settings - see the Configuration section of the https://hub.docker.com/_/php.

It expects all the service files (including composer libraries and the desired `config.yaml`) to be provided in `build/docroot` during the build time.

For a live development you can just build it with an empty `build/docroot` directory and mount service files during `docker run`.

Examples:

* Creating a production image
  ```bash
  # install dependencies skipping development ones and optimizing autoloader
  composer update --no-dev -o
  # prepare the docroot using build/config/arche.yaml as the config.yaml
  mkdir build/docroot && cp -R index.php src vendor build/docroot/ && cp build/config/arche.yaml build/docroot/config.yaml
  # build the image
  docker build --rm -t acdhch/arche-biblatex --build-arg VARIANT=production build
  # try to run it locally
  docker run -d --name arche-biblatex -p 80:80 acdhch/arche-biblatex
  # check if it works locally
  curl -i 'http://127.0.0.1/?id=https%3A%2F%2Fid.acdh.oeaw.ac.at%2Fgtrans'
  # push the image to the registry
  docker push acdhch/arche-biblatex
  # redeploy on ACDH Kubernetes
  curl -X POST 'https://rancher.acdh-dev.oeaw.ac.at/v3/project/{pathToDesiredWorkload}?action=redeploy' -H 'Authorization: Bearer {myRancherApiToken}'
  ```
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
