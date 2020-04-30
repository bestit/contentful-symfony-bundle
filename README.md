# bestit/contentful-bundle

Decorates the client and provides the contentful model as an array as defined in the content type of contentful. 
Additional sugar are the template helper for easy access.

## Installation

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require bestit/contentful-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new BestIt\ContentfulBundle\BestItContentfulBundle(),
        );

        // ...
    }

    // ...
}
```

### Step 3: Configuration Reference

```yaml
best_it_contentful:

    # This field indicates which controller should be used for the routing.
    controller_field:     controller

    # Which field is used to mark the url of the contentful entry?
    routing_field:        slug

    # This content types have a routable page in the project.
    routable_types:       []
    caching:              # Required
        content:

            # Please provider your service id for caching contentful contents.
            service_id:           ~ # Required

            # Please provide the ttl for your content cache in seconds. 0 means forever.
            cache_time:           0
        routing:

            # Please provider your service id for caching contentful routings.
            service_id:           ~ # Required

            # Please provide the ttl for your routing cache in seconds. 0 means forever.
            cache_time:           0

            # If the requested url contains this query parameter, the routing cache will be ignored.
            parameter_against_routing_cache:  ignore-contentful-routing-cache

        # Should the whole contentful cache be cleared every time on an entry reset request? Not recommended
        complete_clear_on_webhook:  false

        # Which cache ids should be resetted everytime?
        collection_consumer:  []

    # Add the content types mainly documented under: <https://www.contentful.com/developers/docs/references/content-management-api/#/reference/content-types>
    content_types:

        # Prototype
        id:
            description:          ~ # Required
            displayField:         ~ # Required
            name:                 ~ # Required

            # Give the logical controller name for the routing, like document under <http://symfony.com/doc/current/routing.html#controller-string-syntax>
            controller:           ~
            fields:               # Required

                # Prototype
                id:
                    linkType:             ~ # One of "Asset"; "Entry"
                    name:                 ~ # Required
                    omitted:              false
                    required:             false
                    items:
                        type:                 ~ # One of "Link"; "Symbol"
                        linkType:             ~ # One of "Asset"; "Entry"
                        validations:
                            linkContentType:      []
                    type:                 ~ # One of "Array"; "Boolean"; "Date"; "Integer"; "Location"; "Link"; "Number"; "Object"; "Symbol"; "Text", Required

                    # Shortcut to handle the editor interface for this field, documentation can be found here: <https://www.contentful.com/developers/docs/references/content-management-api/#/reference/editor-interface>
                    control:              # Required
                        id:                   ~ # One of "assetLinkEditor"; "assetLinksEditor"; "assetGalleryEditor"; "boolean"; "datePicker"; "entryLinkEditor"; "entryLinksEditor"; "entryCardEditor"; "entryCardsEditor"; "numberEditor"; "rating"; "locationEditor"; "objectEditor"; "urlEditor"; "slugEditor"; "ooyalaEditor"; "kalturaEditor"; "kalturaMultiVideoEditor"; "listInput"; "checkbox"; "tagEditor"; "multipleLine"; "markdown"; "singleLine"; "dropdown"; "radio", Required
                        settings:             # Required
                            ampm:                 ~
                            falseLabel:           ~
                            format:               ~
                            helpText:             ~ # Required
                            stars:                ~
                            trueLabel:            ~
                    validations:
                        size:
                            max:                  ~
                            min:                  ~
                        in:                   []
                        linkContentType:      []
                        unique:               ~
```

### Step 4: Enable the caching

This bundle introduces a 2 Layer caching system for all used contentful entries to allow a almost zero request workflow for the
user.  

- Every contentful entry is cached with his contentful id in the configured caching pool. 
- Every query that is executed will be stored in a serialized format and persisted. The serialized query must not be stored in the cache. 

To allow this zero request worklflow this bundle implements a cache warmup mechanismus to prefill the contentful cache
with the entries from contentful. This cache warmup uses the contentful syncronisation api and a combination of simple
queries that only fetched the ids of the result. this id list can be stored in the cache and the implementation will then
use the cached entries from the synronisation process. 

Additionally, new and changed entries will be stored in the cache via a [webhook mechanismus](https://www.contentful.com/developers/docs/concepts/webhooks/) that creates or updates the send entry
in the cache. 

To enable the webhook just import the routing.yml file in yout internal project 
routign configurtion like this:

```yaml
best_it_contentful:
    prefix:   /contentful
    resource: '@BestItContentfulBundle/Resources/config/routing.yml'
```    

To activate the webhooks in contentful follow the guide [guide](https://www.contentful.com/developers/docs/concepts/webhooks/)
and configure the webhook with the following table:

| Type  | Event   | URL    | Description                |
|-------|---------|--------|----------------------------|
| Entry | create  | /fill  | A entry has been created. The cached entry will be created.   |
| Entry | publish | /fill  | A entry has been published. The cached entry will be overwriten. |
| Entry | save    | /fill  | A entry has been saved. The cached entry will be overwriten.     |
| Entry | delete  | /reset | A entry has been deleted. The cached entry will be deleted.  |

The cache warmer is automatically enabled and should run at every call of the following command:

```console
$ ./bin/console cache:warmup
```

or 

```console
$ ./bin/console cache:clear
```

**If you have cache entries which are not matched to the entry ids directly but to your own custom cache ids, you need to 
fill the config value caching.collection_consumer with this custom cache ids to reset them anytime another cache is 
reset.**
Because of technical limitation the raw entry from contentful is not saved in the cache but a parsed array 
representation instead. The parsing of a the fetched entry is possible via individual response parsers that convert the
entry object into an array before it is saved in the cache.

It is possible to implement custom parsers if you need to modify the parsed result. This custom parser can be attached 
to the fetch operation for the entries. If you have attached a custom parser the parsed array representation for
this parser will be returned from the cache instead of the default implementation. 

To make this work you need to register your custom parsers to a content type in the configuration. 
With this configured everytime a entry from contentful with the configured contenttype is saved in the cache,
the parse result of the custom response parser is also saved in the cache. If you fetch a entry from the cache with a 
custom parser the correct result for this parser is returned. If you fetch a result without a parser the default parser 
result is returned.

Add the following configuration to your config file to activate the custom parsers:
 
 ```yaml
    best_it_contentful:
        caching:
            response_parser:
                # The configured response parser for the content type manufacturer
                manufacturer:
                    - 'best_it_contentful.routing.route_collection_response_parser'
                # The configured response parser for the content type simple_page
                simple_page:
                    - 'best_it_contentful.routing.route_collection_response_parser'
                # The configured response parser for the content type startpage
                startpage:
                    - 'best_it_contentful.routing.route_collection_response_parser'
 ```

## Usage

### Contentmodel Creator
 
```console
$php bin/console contentful:create-types`
```

This command copies your configured content-types in your contentful project.

### Client Decorator

```php
<?php

/** @var \BestIt\ContentfulBundle\Service\Delivery\ClientDecorator $clientDecorator */
$clientDecorator = $this->getClient();

$contentType = 'example-type';
$limit = 5;
$where = ['fields.example-slug' => 'example-com'];

if (is_scalar($where)) {
    $entry = $clientDecorator->getEntry($id = $where);
} else {
    $entries = $clientDecorator->getEntries(
        function (\Contentful\Delivery\Query $query) use ($contentType, $limit, $where) {
            $query->setContentType($contentType);

            if ($limit) {
                $query->setLimit($limit);
            }

            array_walk($where, function ($value, $key) use ($query) {
                $query->where($key, $value);
            });

            return $query;
        },
        $cacheId = sha1(__METHOD__ . ':' . $contentType . ':' . serialize($where))
    );
}
```

### Router ###

We provide you with a router if you want to match contentful elements directly through the url to a controller action
 of your app. Just register our Slug-Matcher with your [CMF-Routing-Chain](https://symfony.com/doc/current/cmf/components/routing/chain.html):
 
```yaml
services: 
    app.router.contentful:
        class: BestIt\ContentfulBundle\Routing\ContentfulSlugMatcher
        public: false
        arguments:
            - '@best_it_contentful.cache.pool.routing'
            - '@best_it_contentful.delivery.client'
            - '%best_it_contentful.controller_field%'
            - '%best_it_contentful.routing_field%'
            - '@best_it_contentful.routing.route_collection_response_parser'
            - '%best_it_contentful.cache.parameter_against_routing_cache%'
        calls:
            - [setRoutableTypes, ['%best_it_contentful.routable_types%']]
        tags:
            - { name: router, priority: 0 }  # Adjusted to not override the manual routing done by Symfony
```

### View-Helper

You can use following twig helper for easier access:

1. **get_contentful**: Loads an entry/entries matching your query and returns it. If you load entries, you need to 
save the result array. 
2. **parseMarkdown**: This view helper makes use of [Parsedown](https://github.com/erusev/parsedown) and returns the 
markdown of contentful as html.

## Further Steps

* More documentation
* better unittests
* behat tests

