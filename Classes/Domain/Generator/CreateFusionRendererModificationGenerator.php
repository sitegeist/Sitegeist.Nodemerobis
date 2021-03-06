<?php

declare(strict_types=1);

/*
 * This file is part of the Sitegeist.Noderobis package.
 */

namespace Sitegeist\Nodemerobis\Domain\Generator;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\Package\FlowPackageInterface;
use Sitegeist\Nodemerobis\Domain\Modification\CreateFileModification;
use Sitegeist\Nodemerobis\Domain\Modification\DoNothingModification;
use Sitegeist\Nodemerobis\Domain\Modification\ModificationInterface;
use Sitegeist\Nodemerobis\Domain\Specification\NodeTypeNameSpecification;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;

class CreateFusionRendererModificationGenerator implements ModificationGeneratorInterface
{
    /**
     * @var NodeTypeManager
     * @Flow\Inject
     */
    protected $nodeTypeManager;

    public function generateModification(FlowPackageInterface $package, NodeType $nodeType): ModificationInterface
    {
        if ($nodeType->isOfType('Neos.Neos:Shortcut')) {
            $fusionCode = null;
        } elseif ($nodeType->isOfType('Neos.Neos:Document')) {
            $fusionCode = $this->createDocumentFusionPrototype($package, $nodeType);
        } elseif ($nodeType->isOfType('Neos.Neos:Content')) {
            $fusionCode = $this->createContentFusionPrototype($package, $nodeType);
        } else {
            $fusionCode = null;
        }

        if ($fusionCode) {
            $nodeTypeNameSpecification = NodeTypeNameSpecification::fromString($nodeType->getName());
            $filePath = $package->getPackagePath() . 'Resources/Private/Fusion/Integration/' . implode('/', $nodeTypeNameSpecification->getLocalNameParts()) . '.fusion';
            return new CreateFileModification($filePath, $fusionCode);
        } else {
            return new DoNothingModification();
        }
    }

    /**
     * @param FlowPackageInterface $package
     * @param NodeType $nodeType
     * @return string
     */
    protected function createDocumentFusionPrototype(FlowPackageInterface $package, NodeType $nodeType): string
    {
        $name = $nodeType->getName();
        $propertiesRenderer = $this->generatePropertiesAfxRenderer($nodeType);
        $childNodeRenderer = $this->generateChildrenAfxRenderer($nodeType);
        $packagePath = $package->getPackagePath();

        $fusionCode = <<<EOT
            #
            # Renderer for NodeType {$name}
            #
            # @see https://docs.neos.io/cms/manual/rendering
            #
            prototype({$name}) < prototype(Neos.Neos:Page) {

                head {
                    resources = afx`
                        <link href={StaticResource.uri('{$package->getPackageKey()}', 'Public/Styles/Main.css')} rel="stylesheet" media="all" />
                        <style src={StaticResource.uri('{$package->getPackageKey()}', 'Public/Scripts/Main.js')}></style>
                    `
                }

                body = afx`
                    <div>
                        Autogenerated renderer for NodeType: "{$name}"
                        <ul>
                            <li>Css: {$packagePath}Resources/Public/Styles/Main.css</li>
                            <li>JS: {$packagePath}Resources/Public/Scripts/Main.js</li>
                        </ul>

                        {$this->indent($propertiesRenderer, 12)}

                        {$this->indent($childNodeRenderer, 12)}
                    </div>
                `
            }
            EOT;
        return $fusionCode;
    }

    /**
     * @param FlowPackageInterface $package
     * @param NodeType $nodeType
     * @return string
     */
    protected function createContentFusionPrototype(FlowPackageInterface $package, NodeType $nodeType): string
    {
        $name = $nodeType->getName();
        $propertiesRenderer = $this->generatePropertiesAfxRenderer($nodeType);
        $childNodeRenderer = $this->generateChildrenAfxRenderer($nodeType);

        $fusionCode = <<<EOT
            #
            # Renderer for NodeType {$name}
            #
            # @see https://docs.neos.io/cms/manual/rendering
            #
            prototype({$name}) < prototype(Neos.Neos:ContentComponent) {

                renderer = afx`
                    <div>
                        <p>
                            Autogenerated renderer for NodeType: {$name}
                        </p>

                        {$this->indent($propertiesRenderer, 12)}

                        {$this->indent($childNodeRenderer, 12)}
                    </div>
                `
            }
            EOT;
        return $fusionCode;
    }

    protected function generatePropertiesAfxRenderer(NodeType $nodeType): string
    {
        $resultParts = [];
        foreach ($nodeType->getProperties() as $name => $propertyConfiguration) {
            if (str_starts_with($name, '_')) {
                continue;
            }

            $name = $propertyConfiguration['label'] ?? $name;

            if ($propertyConfiguration['ui']['inlineEditable'] ?? false) {
                $renderer = '<Neos.Neos:Editable property="' . $name . '" />';
            } else {
                $renderer = '{q(node).property("' . $name . '")}';
            }

            $resultParts[] = <<<EOT
                    <dt>
                        {$name}
                    </dt>
                    <dd>
                        {$renderer}
                    </dd>
                EOT;
        }
        if (count($resultParts) > 0) {
            return 'Properties:<br/>' . PHP_EOL .  ' <dl>' . PHP_EOL . implode(PHP_EOL, $resultParts) . PHP_EOL . '</dl>';
        }
        return '';
    }

    protected function generateChildrenAfxRenderer(NodeType $nodeType): string
    {
        $resultParts = [];
        foreach ($nodeType->getAutoCreatedChildNodes() as $name => $childNodeType) {
            if ($childNodeType->isOfType('Neos.Neos:Document')) {
                $renderer = '<Neos.Neos:NodeLink node={q(node).children(' . $name . ')} >' . $name . '</Neos.Neos:NodeLink>';
            } elseif ($childNodeType->isOfType('Neos.Neos:ContentCollection')) {
                $renderer = '<Neos.Neos:ContentCollection nodePath="' . $name . '" />';
            } elseif ($childNodeType->isOfType('Neos.Neos:Content')) {
                $renderer = '<Neos.Neos:ContentCase @context.node={q(node).children(' . $name . ')} />';
            } else {
                $renderer = '<!-- no clue how to render node of type ' . $childNodeType->getName() . ' -->';
            }

            $resultParts[] = <<<EOT
                    <dt>
                        {$name}
                    </dt>
                    <dd>
                        {$renderer}
                    </dd>
                EOT;
        }
        if (count($resultParts) > 0) {
            return 'ChildNodes:<br/>' . PHP_EOL . '<dl>' . PHP_EOL . implode(PHP_EOL, $resultParts) . PHP_EOL . '</dl>';
        }
        return '';
    }

    protected function indent(string $text, int $numSpaces = 4): string
    {
        $padding = '';
        for ($i = 0; $i < $numSpaces; $i++) {
            $padding .= ' ';
        }
        return implode(PHP_EOL . $padding, explode(PHP_EOL, $text));
    }
}
