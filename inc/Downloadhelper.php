<?php
/**
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * This class provides function to download documents from WeArePlanet
 */
class WeArePlanetDownloadhelper
{

    /**
     * Downloads the transaction's invoice PDF document.
     */
    public static function downloadInvoice($order)
    {
        $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo != null && in_array(
            $transactionInfo->getState(),
            array(
                \WeArePlanet\Sdk\Model\TransactionState::COMPLETED,
                \WeArePlanet\Sdk\Model\TransactionState::FULFILL,
                \WeArePlanet\Sdk\Model\TransactionState::DECLINE
            )
        )) {
            $service = new \WeArePlanet\Sdk\Service\TransactionService(
                WeArePlanetHelper::getApiClient()
            );
            $document = $service->getInvoiceDocument(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            self::download($document);
        }
    }

    /**
     * Downloads the transaction's packing slip PDF document.
     */
    public static function downloadPackingSlip($order)
    {
        $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
        if ($transactionInfo != null &&
            $transactionInfo->getState() == \WeArePlanet\Sdk\Model\TransactionState::FULFILL) {
            $service = new \WeArePlanet\Sdk\Service\TransactionService(
                WeArePlanetHelper::getApiClient()
            );
            $document = $service->getPackingSlip($transactionInfo->getSpaceId(), $transactionInfo->getTransactionId());
            self::download($document);
        }
    }

    /**
     * Sends the data received by calling the given path to the browser and ends the execution of the script
     *
     * @param string $path
     */
    protected static function download(\WeArePlanet\Sdk\Model\RenderedDocument $document)
    {
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $document->getTitle() . '.pdf"');
        header('Content-Description: ' . $document->getTitle());
        echo WeArePlanetTools::base64Decode($document->getData());
        exit();
    }
}
