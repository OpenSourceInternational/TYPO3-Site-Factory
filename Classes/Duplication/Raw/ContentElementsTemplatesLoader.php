<?php
declare(strict_types=1);

namespace Romm\SiteFactory\Duplication\Raw;

use TYPO3\CMS\Core\Core\Environment;

/**
 * Class ContentElementsTemplatesLoader
 *
 * @author Vladimir Cherednichenko <vovacherednichenko@o-s-i.org>
 */
class ContentElementsTemplatesLoader
{
    /**
     * @return array
     */
    public function load(): array
    {
        $extensions = $this->getExtList();
        $extDir = Environment::getPublicPath() . '/typo3conf/ext/';
        $templates = [];

        foreach ($extensions as $extension) {
            $templatesDir = $extDir . $extension . '/Resources/Private/Templates/Content';
            $extName = str_replace('_', '', $extension);

            if (!is_dir($templatesDir)) {
                continue;
            }

            $templates[$extName] = [
                'name' => $extension,
                'templates' => []
            ];

            $contentElements = scandir($templatesDir);

            foreach ($contentElements ?: [] as $contentElement) {
                if ($contentElement === '.' || $contentElement === '..') {
                    continue;
                }

                $contentElementName = mb_strtolower(str_replace('.html', '', $contentElement));
                // $contentElementPath = 'EXT:' . $extension . '/Resources/Private/Templates/Content/' . $contentElement;
                $templates[$extName]['templates'][$contentElementName] = $contentElement;
            }
        }

        return $templates;
    }

    /**
     * @return array
     */
    public function getExtList(): array
    {
        $list = [];
        $extDir = Environment::getPublicPath() . '/typo3conf/ext/';
        $extensions = scandir($extDir);

        foreach ($extensions ?: [] as $extension) {
            if ($extension === '.' || $extension === '..') {
                continue;
            }

            $list[] = $extension;
        }

        return $list;
    }
}
