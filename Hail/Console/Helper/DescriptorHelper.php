<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Console\Helper;

use Hail\Console\Descriptor\DescriptorInterface;
use Hail\Console\Descriptor\JsonDescriptor;
use Hail\Console\Descriptor\MarkdownDescriptor;
use Hail\Console\Descriptor\TextDescriptor;
use Hail\Console\Descriptor\XmlDescriptor;
use Hail\Console\Output\OutputInterface;
use Hail\Console\Exception\InvalidArgumentException;

/**
 * This class adds helper method to describe objects in various formats.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class DescriptorHelper extends Helper
{
    /**
     * @var DescriptorInterface[]
     */
    private $descriptors = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this
            ->register('txt', new TextDescriptor())
            ->register('xml', new XmlDescriptor())
            ->register('json', new JsonDescriptor())
            ->register('md', new MarkdownDescriptor())
        ;
    }

    /**
     * Describes an object if supported.
     *
     * Available options are:
     * * format: string, the output format name
     * * raw_text: boolean, sets output type as raw
     *
     * @param OutputInterface $output
     * @param object          $object
     * @param array           $options
     *
     * @throws InvalidArgumentException when the given format is not supported
     */
    public function describe(OutputInterface $output, $object, array $options = array())
    {
        $options = array_merge(array(
            'raw_text' => false,
            'format' => 'txt',
        ), $options);

        if (!isset($this->descriptors[$options['format']])) {
            throw new InvalidArgumentException(sprintf('Unsupported format "%s".', $options['format']));
        }

        $descriptor = $this->descriptors[$options['format']];
        $descriptor->describe($output, $object, $options);
    }

    /**
     * Registers a descriptor.
     *
     * @param string              $format
     * @param DescriptorInterface $descriptor
     *
     * @return $this
     */
    public function register($format, DescriptorInterface $descriptor)
    {
        $this->descriptors[$format] = $descriptor;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'descriptor';
    }
}