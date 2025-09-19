<?php
/**
 * VendorDataManager - Sistema simple con hook directo desde AEATApiBridge
 * 
 * FLUJO SIMPLE:
 * 1. AEATApiBridge ejecuta hook 'factupress_before_generate_register'
 * 2. VendorDataManager establece contexto con order_id
 * 3. Intercepta ÚNICAMENTE la siguiente llamada a get_option de Factupress
 * 4. Sustituye datos del vendor y limpia contexto
 * 
 * @package SchoolManagement\Integration
 * @since 2.0.0 - Simplificado: solo hook directo
 */

namespace SchoolManagement\Integration;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * VendorDataManager - Solo hook desde AEATApiBridge
 */
class VendorDataManager
{
    private $current_vendor_id = null;
    private $current_order_id = null;

    public function __construct()
    {
        // ÚNICO HOOK: Solo desde AEATApiBridge - aquí hacemos todo el mapeo
        add_action('factupress_before_generate_register', [$this, 'setOrderContextFromAEAT'], 1, 2);
        
        // NUEVOS HOOKS: Para interceptar y personalizar numeración de PDF
        add_filter('wpo_wcpdf_document_number_settings', [$this, 'customizeInvoiceNumberFormat'], 10, 3);
        add_action('wpo_wcpdf_after_pdf_created', [$this, 'incrementVendorInvoiceNumber'], 10, 2);
        add_filter('wpo_wcpdf_document_number', [$this, 'applyVendorNumbering'], 10, 3);
        // Hook adicional para formato completo del número
        add_filter('wpo_wcpdf_format_document_number', [$this, 'applyVendorNumberingFormat'], 999, 4);
        
        // Hook para interceptar antes de la creación del documento
        add_action('wpo_wcpdf_before_pdf', [$this, 'setupVendorContextForPDF'], 5, 2);
        
        // Hook adicional para asegurar que la numeración se aplique en todas las situaciones
        add_filter('wpo_wcpdf_get_document_number', [$this, 'overrideDocumentNumber'], 100, 2);
    }

    /**
     * Establece contexto desde AEATApiBridge y mapea datos del vendor - TODO EN UNO
     */
    public function setOrderContextFromAEAT($order_id, $document_type)
    {
        // Obtener datos del vendor usando ACF
        $vendor_data = $this->getVendorDataFromOrder($order_id);
        if (!$vendor_data) {
            return;
        }

        // Establecer contexto interno
        $this->current_order_id = $order_id;
        $this->current_vendor_id = $vendor_data['vendor_id'] ?? null;

        // Solo mapear si es factura
        if ($document_type === 'invoice') {
            $this->mapVendorDataToFactupress($vendor_data);
        }
    }