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
    caching:

        # Please provider your service id for caching contentful contents.
        content:              ~ # Required

        # If the requested url contains this query parameter, the routing cache will be ignored.
        parameter_against_routing_cache:  ignore-contentful-routing-cache

        # Please provider your service id for caching contentful routings.
        routing:              ~ # Required

        # Which cache ids should be resetted everytime?
        collection_consumer:  []
        
        # Should the whole cache pool be cleared after an entry reset over the webhook is detected
        complete_clear_on_webhook: false

    # Add the content types mainly documented under: <https://www.contentful.com/developers/docs/references/content-management-api/#/r
eference/content-types>
    content_types:

        # Prototype
        id:
            description:          ~ # Required
            displayField:         ~ # Required
            name:                 ~ # Required

            # Give the logical controller name for the routing, like document under <http://symfony.com/doc/current/routing.html#contr
oller-string-syntax>
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
                    type:                 ~ # One of "Array"; "Boolean"; "Date"; "Integer"; "Location"; "Link"; "Number"; "Object"; "S
ymbol"; "Text", Required

                    # Shortcut to handle the editor interface for this field, documentation can be found here: <https://www.contentful
.com/developers/docs/references/content-management-api/#/reference/editor-interface>
                    control:              # Required
                        id:                   ~ # One of "assetLinkEditor"; "assetLinksEditor"; "assetGalleryEditor"; "boolean"; "date
Picker"; "entryLinkEditor"; "entryLinksEditor"; "entryCardEditor"; "entryCardsEditor"; "numberEditor"; "rating"; "locationEditor"; "ob
jectEditor"; "urlEditor"; "slugEditor"; "ooyalaEditor"; "kalturaEditor"; "kalturaMultiVideoEditor"; "listInput"; "checkbox"; "tagEdito
r"; "multipleLine"; "markdown"; "singleLine"; "dropdown"; "radio", Required
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

### Step 4: Enable the cache reset webhook

**Every contentful entry is cached forever with this bundle, but ...**

You can use the [contentful webhooks](https://www.contentful.com/developers/docs/concepts/webhooks/) to reset your 
cache entries. 

```yaml
best_it_contentful:
    prefix:   /bestit/contentful
    resource: "@BestItContentfulBundle/Controller"
    type:     annotation
```    

Just add the reset controller to your routing (_we suggest to protected it with a http auth password_) and input this
 url in your contentful webhook config and you are ready to go.


The most simple cache reset is the direct reset on an id or the array of the ids from an collection (used as cache 
tags). 

**If you have cache entries which are not matched to the entry ids directly but to your own custom cache ids, you need to 
fill the config value caching.collection_consumer with this custom cache ids to reset them anytime another cache is 
reset.**

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

