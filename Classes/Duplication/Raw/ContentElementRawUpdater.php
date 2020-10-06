<?php
declare(strict_types=1);

namespace Romm\SiteFactory\Duplication\Raw;

use FluidTYPO3\Flux\Form;
use Romm\SiteFactory\Utility\TypoScriptUtility;
use Stratis\StratisServices\Service\QueryBuilderService;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidControllerNameException;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\View\StandaloneView;
use FluidTYPO3\Flux\ViewHelpers\FormViewHelper;

/**
 * Class ContentElementRawUpdater
 *
 * @author Vladimir Cherednichenko <vovacherednichenko@o-s-i.org>
 */
class ContentElementRawUpdater
{
    /**
     * @var QueryBuilderService
     */
    protected $qbService;

    /**
     * @var FlexFormService
     */
    protected $flexFormService;

    /**
     * @var FlexFormTools
     */
    protected $flexFormTools;

    /**
     * @var array
     */
    protected $templates = [];

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var array
     */
    protected $idsMapping = [];

    /**
     * @var int
     */
    protected $rootId;

    /**
     * @var RenderingContext
     */
    protected $renderingContext;

    /**
     * FlexFormRawUpdater constructor.
     *
     * @param QueryBuilderService            $qbService
     * @param FlexFormService                $flexFormService
     * @param FlexFormTools                  $flexFormTools
     * @param ContentElementsTemplatesLoader $contentElementsTemplatesLoader
     *
     * @throws InvalidControllerNameException
     */
    public function __construct(
        QueryBuilderService $qbService,
        FlexFormService $flexFormService,
        FlexFormTools $flexFormTools,
        ContentElementsTemplatesLoader $contentElementsTemplatesLoader
    )
    {
        $this->qbService = $qbService;
        $this->flexFormService = $flexFormService;
        $this->flexFormTools = $flexFormTools;

        $this->templates = $contentElementsTemplatesLoader->load();
        $this->config = TypoScriptUtility::getTypoScriptConfiguration($this->rootId);
        $this->renderingContext = $this->getRenderingContext();
    }

    /**
     * @param int   $rootId
     * @param array $idsMapping
     */
    public function update(int $rootId, array $idsMapping)
    {
        $this->idsMapping = $idsMapping;
        $this->rootId = $rootId;
        $newRecordsIds = array_values($idsMapping['tt_content']);

        $rows = $this->qbService->setTable('tt_content')
            ->fetchAllByParams([
                'uid' => $newRecordsIds
            ]);

        foreach ($rows as $row) {
            $this->updateContentElement($row);
        }
    }

    /**
     * @param array $row
     *
     * @return array
     */
    protected function loadFormData(array $row): ?array
    {
        if (!$row['list_type']) {
            return $this->loadFormDataFromFluxBasedContentElement($row);
        }

        return $this->loadFormDataFromFlexForm($row);
    }

    /**
     * @param array $row
     *
     * @return array
     */
    protected function loadFormDataFromFlexForm(array $row): ?array
    {
        if (!array_key_exists($row['list_type'], $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'])) {
            return null;
        }

        $flexFormPathKey = $row['list_type'] . ',' . $row['CType'];
        $flexFormPath = $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][$flexFormPathKey];

        if (!$flexFormPath) {
            return null;
        }

        $flexFormAbsPath = GeneralUtility::getFileAbsFileName(str_replace('FILE:', '', $flexFormPath));

        if (!is_file($flexFormAbsPath)) {
            return null;
        }

        $flexFormContent = file_get_contents($flexFormAbsPath);
        $content = GeneralUtility::xml2array($flexFormContent);
        $content = $this->removeTCEForms($content);

        return $content;
    }

    /**
     * @param array $formData
     *
     * @return array
     */
    protected function removeTCEForms(array $formData): array
    {
        foreach ($formData as $key => $value) {
            if ($key === 'TCEforms') {
                $formData = array_merge($formData, $formData['TCEforms']);
                unset($formData['TCEforms']);
                continue;
            }

            if (is_array($value)) {
                $formData[$key] = $this->removeTCEForms($value);
            }
        }

        return $formData;
    }

    /**
     * @param array $row
     *
     * @return array
     */
    protected function loadFormDataFromFluxBasedContentElement(array $row): ?array
    {
        [$extName, $templateName] = explode('_', $row['CType'], 2);

        if (!array_key_exists($extName, $this->templates) || !array_key_exists($templateName, $this->templates[$extName]['templates'])) {
            return null;
        }

        try {
            $view = $this->getStandaloneView(
                $this->templates[$extName]['name'],
                $this->templates[$extName]['templates'][$templateName]
            );
        } catch (InvalidControllerNameException $ex) {
            return null;
        }

        $view->renderSection('Configuration');

        /** @var Form $form */
        $form = $view->getRenderingContext()->getViewHelperVariableContainer()->get(FormViewHelper::class, 'form');

        return $form->build();
    }

    /**
     * @param array $row
     */
    protected function updateContentElement(array $row)
    {
        $piFlexForm = $this->getUpdatedPiFlexForm($row);

        $pageIds = explode(',', $row['pages'] ?? '');
        $newPageIds = [];

        foreach ($pageIds as $id) {
            $newPageIds[] = $this->idsMapping['pages'][(int) $id] ?? $id;
        }

        $this->qbService->setTable('tt_content')
            ->updateFromArray($row['uid'], [
                'pages' => implode(',', $newPageIds),
                'pi_flexform' => $piFlexForm
            ]);
    }

    /**
     * @param array $row
     *
     * @return string
     */
    protected function getUpdatedPiFlexForm(array $row): string
    {
        $formData = $this->loadFormData($row);

        if (!$formData || !$row['pi_flexform']) {
            return (string) $row['pi_flexform'];
        }

        $content = GeneralUtility::xml2array($row['pi_flexform']);

        foreach ($content['data'] as $sectionKey => $section) {
            $fields = $formData['sheets'][$sectionKey]['ROOT']['el'];

            foreach ($section['lDEF'] as $fieldKey => $field) {
                if (!array_key_exists($fieldKey, $fields ?? [])) {
                    continue;
                }

                $fieldConfig = $fields[$fieldKey]['config'];

                // Panel processing
                // TODO: refactor this
                if ($fields[$fieldKey]['section']) {
                    foreach ($fields[$fieldKey]['el'] as $elKey => $elData) {
                        foreach ($elData['el'] as $panelFieldKey => $panelFieldData) {
                            foreach ($content['data'][$sectionKey]['lDEF'][$fieldKey]['el'] as $contentSectionKey => $contentSectionData) {
                                foreach ($contentSectionData[$elKey]['el'] as $contentElKey => $contentElData) {
                                    $value = $this->getFieldValue($panelFieldData['config'], $contentElData);
                                    $content['data'][$sectionKey]['lDEF'][$fieldKey]['el'][$contentSectionKey][$elKey]['el'][$contentElKey]['vDEF'] = $value;
                                }
                            }
                        }
                    }

                    continue;
                }

                // Field processing
                $content['data'][$sectionKey]['lDEF'][$fieldKey]['vDEF'] = $this->getFieldValue($fieldConfig, $field);
            }
        }

        return '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' . $this->flexFormTools->flexArray2Xml($content);
    }

    /**
     * @param array $fieldConfig
     * @param array $field
     *
     * @return mixed|string
     */
    protected function getFieldValue(array $fieldConfig, array $field)
    {
        $value = $field['vDEF'];

        if ($fieldConfig['internal_type'] === 'db') {
            $ids = explode(',', $value);
            $newIds = [];

            foreach ($ids as $id) {
                $newIds[] = $this->idsMapping[$fieldConfig['allowed']][(int) $id] ?? $id;
            }

            return implode(',', $newIds);
        }

        if (in_array($fieldConfig['type'],['radio', 'text', 'select', 'check'])) {
            // DO nothing
            return $value;
        }

        // Replace links
        if ($fieldConfig['type'] === 'input' && is_array($fieldConfig['wizards']['link'])) {
            if (preg_match('/^\d+$/', $value)) {
                return (string) ($this->idsMapping['pages'][(int) $value] ?? $value);
            }

            return $value;
        }

        if ($fieldConfig['type'] === 'inline') {
            $ids = explode(',', $value);
            $newIds = [];

            foreach ($ids as $id) {
                $newIds[] = $this->idsMapping[$fieldConfig['foreign_table']][(int) $id] ?? $id;
            }

            return implode(',', $newIds);
        }

        return $value;
    }

    /**
     * @return RenderingContext
     *
     * @throws InvalidControllerNameException
     */
    protected function getRenderingContext(): RenderingContext
    {
        $request = GeneralUtility::makeInstance(Request::class);
        $request->setControllerName('Content');

        $controllerContext = GeneralUtility::makeInstance(ControllerContext::class);
        $controllerContext->setRequest($request);

        $renderingContext = GeneralUtility::makeInstance(RenderingContext::class);
        $renderingContext->setControllerContext($controllerContext);
        $renderingContext->setControllerName('Content');

        return $renderingContext;
    }

    /**
     * @param string $extName
     * @param string $templateName
     *
     * @return StandaloneView
     */
    protected function getStandaloneView(string $extName, string $templateName): StandaloneView
    {
        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(ObjectManager::class)->get(StandaloneView::class);

        $pluginName = 'tx_' . str_replace('_', '', $extName);

        $view->setRenderingContext($this->renderingContext);
        $view->setLayoutRootPaths($this->config['plugin'][$pluginName]['view']['layoutRootPaths'] ?? ['EXT:' . $extName . '/Resources/Private/Layouts']);
        $view->setPartialRootPaths($this->config['plugin'][$pluginName]['view']['partialRootPaths'] ?? ['EXT:' . $extName . '/Resources/Private/Partials']);
        $view->setTemplateRootPaths($this->config['plugin'][$pluginName]['view']['templateRootPaths'] ?? ['EXT:' . $extName . '/Resources/Private/Templates']);
        $view->setTemplate($templateName);

        return $view;
    }
}
