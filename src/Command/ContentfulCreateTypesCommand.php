<?php

namespace BestIt\ContentfulBundle\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ContentfulCreateTypesCommand
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 * @subpackage Command
 * @todo Refactor and unittest.
 * @version $id$
 */
class ContentfulCreateTypesCommand extends ContainerAwareCommand
{
    /**
     * Configures this command.
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('contentful:create-types')
            ->setDescription('Creates the defined contentful types.')
            // https://www.contentful.com/developers/docs/references/authentication/#the-management-api
            ->addArgument('token', InputArgument::REQUIRED, 'The OAUTH Token of the content management api.');
    }

    /**
     * Executes the command.
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $structure = $this->moveIdFromKeyToValue(
            $this->getContainer()->getParameter('best_it_contentful.content_types')
        );

        $output->write(PHP_EOL);

        $status = $this->setContentTypes(
            $this->sortTypeDepsToTop($structure),
            $this->getContentfulClient($input->getArgument('token')),
            new ProgressBar($output),
            $this->getContainer()->get('best_it_contentful.delivery.client')->getSpace()->getId()
        );

        $output->write(PHP_EOL . PHP_EOL);

        $return = 0;
        $table = new Table($output);

        $table->setHeaders(['type', 'status']);

        foreach ($status as $typeId => $typeStatus) {
            if ($error = (bool)$typeStatus) {
                $return = 1;
            }

            $table->addRow([
                $typeId,
                sprintf(
                    !$error ? '<info>%s</info>' : '<comment>%s</comment>',
                    !$error ? 'Success' : $typeStatus
                )
            ]);
        }

        $table->render();

        return $return;
    }

    /**
     * Extracts the controls for the field.
     * @param array $settings
     * @return array
     */
    protected function extractFieldControls(array $settings):array
    {
        $controls = [];

        foreach ($settings['fields'] as &$field) {
            if (@$field['control']) {
                $controls[] = [
                    'fieldId' => $field['id'],
                    'widgetId' => $field['control']['id'],
                    'settings' => $field['control']['settings'] ?? []
                ];

                unset($field['control']);
            }
        }
        return [$controls, $settings];
    }

    /**
     * Returns a contentful client for the management api.
     * @param string $key
     * @return Client
     */
    protected function getContentfulClient(string $key):Client
    {
        $client = new Client([
            'base_uri' => 'https://api.contentful.com/',
            'headers' => [
                'Authorization' => 'Bearer ' . $key
            ]
        ]);
        return $client;
    }

    /**
     * Returns a version of the content type.
     * @param Client $client
     * @param string $space
     * @param string $typeId
     * @return void|int
     */
    protected function getVersionOfContentType(Client $client, string $space, string $typeId)
    {
        $contentResponse = $client->request('GET', "spaces/{$space}/content_types");
        $return = null;

        if ($contentResponse->getStatusCode() === 200) {
            $typeList = json_decode($contentResponse->getBody()->getContents());

            foreach ($typeList->items as $index => $type) {
                if ($type->sys->id === $typeId) {
                    $return = $type->sys->version;
                }
            }
        }
        return $return;
    }

    /**
     * Returns the version for the editor interface.
     * @param Client $client
     * @param string $space
     * @param string $typeId
     * @return int
     */
    protected function getVersionOfEditorInterface(Client $client, string $space, string $typeId): int
    {
        $interfaceResponse = $client->request('GET', "spaces/{$space}/content_types/{$typeId}/editor_interface");

        return json_decode($interfaceResponse->getBody()->getContents())->sys->version;
    }

    protected function moveIdFromKeyToValue(array $structure): array
    {
        array_walk($structure, function (&$contentType, $typeId) {
            $contentType['id'] = $typeId;

            array_walk($contentType['fields'], function (&$contentTypeField, $fieldId) {
                $contentTypeField['id'] = $fieldId;
            });

            $contentType['fields'] = array_values($contentType['fields']);
        });

        return $structure;
    }

    /**
     * Publishes the given content type.
     * @param Client $client
     * @param int $oldVersion
     * @param string $space
     * @param string $typeId
     * @return void
     */
    protected function publishContentType(Client $client, int $oldVersion, string $space, string $typeId)
    {
        $client->request('PUT', "spaces/{$space}/content_types/{$typeId}/published", [
            'headers' => [
                // Contentful is not fast enough to return the new version with a following get, so increment it
                // manually.
                'X-Contentful-Version' => $oldVersion + 1
            ]
        ]);
    }

    /**
     * Saves the content type in contentful.
     * @param Client $client
     * @param array $settings
     * @param string $space
     * @param string $typeId
     * @return int|void
     */
    protected function setContentType(Client $client, array $settings, string $space, string $typeId)
    {
        array_walk($settings['fields'], function (array &$field) {
            if (!$field['validations']) {
                unset($field['validations']);
            } else {
                array_walk($field['validations'], function (array &$validation) {
                    // Fix the bug, that symfony adds a wrong und undefined node.
                    if (array_key_exists('linkContentType', $validation) && !$validation['linkContentType']) {
                        unset($validation['linkContentType']);
                    }
                    if (array_key_exists('in', $validation) && !$validation['in']) {
                        unset($validation['in']);
                    }
                });
            }
        });

        $client->request('PUT', "spaces/{$space}/content_types/{$typeId}", [
            'json' => $settings,
            'headers' => [
                'X-Contentful-Version' => $oldVersion = $this->getVersionOfContentType($client, $space, $typeId)
            ]
        ]);

        return $oldVersion;
    }

    /**
     * Saves the content types in contentful.
     * @param array $structure
     * @param Client $client
     * @param ProgressBar $bar
     * @param string $space
     * @return array
     */
    protected function setContentTypes(array $structure, Client $client, ProgressBar $bar, string $space): array
    {
        $return = [];

        $bar->start(count($structure));

        foreach ($structure as $settings) {
            $bar->advance();

            $typeId = $settings['id'];
            unset($settings['id']);

            try {
                list($controls, $settings) = $this->extractFieldControls($settings);

                $oldVersion = $this->setContentType($client, $settings, $space, $typeId);

                $this->publishContentType($client, (int)$oldVersion, $space, $typeId);

                if ($controls) {
                    $this->updateEditorInterface($client, $controls, $space, $typeId);
                }

                $return[$typeId] = '';
            } catch (ClientException $exc) {
                $response = $exc->getResponse();

                $return[$typeId] = $response ? $response->getBody()->getContents() : $exc->getMessage();
            }
        }

        $bar->finish();

        return $return;
    }

    /**
     * Sorts for the dependencies.
     * @param array $settings
     * @return array
     */
    protected function sortTypeDepsToTop(array $settings): array
    {
        uasort($settings, function ($type1, $type2) {
            $return = 0;

            foreach ($type1['fields'] as $field) {
                // Check if the id of type2 is part of type1s validation. If yes, move type1 down.
                if ((strtolower($field['type']) === 'array') && (strtolower($field['items']['type']) === 'link') &&
                    (strtolower($field['items']['linkType']) === 'entry') && (@$field['items']['validations'])
                ) {
                    foreach ($field['items']['validations'] as $validation) {
                        if ((@$validation['linkContentType']) &&
                            (in_array($type2['id'], $validation['linkContentType']))
                        ) {
                            $return = 1;
                            break 2;
                        }
                    }
                }
            }

            if (!$return) {
                // Check if the id of type1 is part of type2s validation. If yes, move type2 up.
                foreach ($type2['fields'] as $field) {
                    if ((strtolower($field['type']) === 'array') && (strtolower($field['items']['type']) === 'link') &&
                        (strtolower($field['items']['linkType']) === 'entry') && (@$field['items']['validations'])
                    ) {
                        foreach ($field['items']['validations'] as $validation) {
                            if ((@$validation['linkContentType']) &&
                                (in_array($type1['id'], $validation['linkContentType']))
                            ) {
                                $return = -1;

                                break 2;
                            }
                        }
                    }
                }
            }

            return $return;
        });

        return $settings;
    }

    /**
     * @param Client $client
     * @param array $controls
     * @param string $space
     * @param string $typeId
     */
    protected function updateEditorInterface(Client $client, array $controls, string $space, string $typeId)
    {
        $client->request('PUT', "spaces/{$space}/content_types/{$typeId}/editor_interface", [
            'json' => ['controls' => $controls],
            'headers' => [
                'X-Contentful-Version' => $this->getVersionOfEditorInterface($client, $space, $typeId)
            ]
        ]);
    }
}
