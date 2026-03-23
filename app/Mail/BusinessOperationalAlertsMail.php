<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessOperationalAlertsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $businessName,
        public string $subjectLine,
        public array $payload,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $lowStockItems = $this->payload['low_stock']['items'] ?? [];
        $expirationItems = $this->payload['expiration']['items'] ?? [];
        $generatedAt = e((string) ($this->payload['generated_at'] ?? now()->format('Y-m-d H:i')));
        $dashboardUrl = e(url('/dashboard'));
        $productsUrl = e(url('/products'));
        $lowStockCount = count($lowStockItems);
        $expirationCount = count($expirationItems);
        $expiredCount = (int) ($this->payload['expiration']['summary']['expired'] ?? 0);

        $html = [
            '<!DOCTYPE html>',
            '<html lang="es">',
            '<head>',
            '<meta charset="UTF-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
            '<title>'.e($this->subjectLine).'</title>',
            '<style>',
            'body{margin:0;padding:0;background:#e2e8f0;font-family:Arial,sans-serif;color:#0f172a;}',
            'table{border-collapse:collapse;}',
            '.wrapper{width:100%;background:#e2e8f0;padding:24px 12px;}',
            '.shell{width:100%;max-width:720px;margin:0 auto;background:#0f172a;border-radius:24px;overflow:hidden;}',
            '.hero{padding:32px 28px;background:linear-gradient(135deg,#082f49 0%,#0f172a 55%,#1e293b 100%);color:#f8fafc;}',
            '.hero-kicker{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(34,211,238,0.16);color:#a5f3fc;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;}',
            '.hero-title{margin:16px 0 10px;font-size:28px;line-height:1.2;font-weight:700;}',
            '.hero-copy{margin:0;color:#cbd5e1;font-size:15px;line-height:1.6;}',
            '.content{background:#ffffff;padding:28px;}',
            '.summary-grid{width:100%;margin:0 0 24px;}',
            '.summary-card{width:33.33%;padding:0 6px 12px;vertical-align:top;}',
            '.summary-card-inner{border:1px solid #dbeafe;border-radius:16px;background:#f8fafc;padding:16px 14px;}',
            '.summary-label{margin:0 0 8px;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;}',
            '.summary-value{margin:0;color:#0f172a;font-size:26px;font-weight:700;line-height:1.1;}',
            '.summary-note{margin:6px 0 0;color:#64748b;font-size:12px;line-height:1.4;}',
            '.section{margin-top:24px;}',
            '.section-header{margin:0 0 12px;font-size:18px;font-weight:700;line-height:1.3;}',
            '.section-copy{margin:0 0 14px;color:#475569;font-size:14px;line-height:1.6;}',
            '.data-table{width:100%;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;}',
            '.data-table th{padding:14px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;}',
            '.data-table td{padding:14px 16px;border-bottom:1px solid #f1f5f9;font-size:14px;line-height:1.45;color:#0f172a;}',
            '.data-table tr:last-child td{border-bottom:none;}',
            '.mobile-cards{display:none;}',
            '.mobile-card{margin-bottom:12px;border:1px solid #e2e8f0;border-radius:16px;background:#ffffff;padding:14px 14px 12px;}',
            '.mobile-card:last-child{margin-bottom:0;}',
            '.mobile-card-title{margin:0;font-size:15px;line-height:1.4;font-weight:700;color:#0f172a;}',
            '.mobile-card-meta{margin:8px 0 0;color:#475569;font-size:13px;line-height:1.55;}',
            '.mobile-card-row{margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9;}',
            '.mobile-card-label{display:block;margin-bottom:4px;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;}',
            '.pill{display:inline-block;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:700;}',
            '.pill-danger{background:#fee2e2;color:#991b1b;}',
            '.pill-warn{background:#fef3c7;color:#92400e;}',
            '.muted{color:#64748b;font-size:13px;}',
            '.cta{margin-top:24px;border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);padding:18px;}',
            '.cta-title{margin:0 0 8px;font-size:16px;font-weight:700;color:#0f172a;}',
            '.cta-copy{margin:0;color:#334155;font-size:14px;line-height:1.6;}',
            '.cta-link{color:#0f4c81;word-break:break-all;}',
            '.footer{padding:0 28px 28px;background:#ffffff;color:#64748b;font-size:12px;line-height:1.6;}',
            '@media only screen and (max-width: 640px){',
            '.wrapper{padding:0;background:#0f172a;}',
            '.shell{border-radius:0;}',
            '.hero{padding:24px 20px;}',
            '.hero-title{font-size:24px;}',
            '.content{padding:20px;}',
            '.summary-card{display:block;width:100%;padding:0 0 12px;}',
            '.desktop-table{display:none!important;}',
            '.mobile-cards{display:block!important;}',
            '}',
            '</style>',
            '</head>',
            '<body>',
            '<div class="wrapper">',
            '<div class="shell">',
            '<div class="hero">',
            '<span class="hero-kicker">ComerStock</span>',
            '<h1 class="hero-title">Alertas operativas de '.e($this->businessName).'</h1>',
            '<p class="hero-copy">Generado el '.$generatedAt.'. Revisamos stock, productos agotados y lotes con vencimiento para este comercio.</p>',
            '</div>',
            '<div class="content">',
            '<table role="presentation" class="summary-grid"><tr>',
            '<td class="summary-card"><div class="summary-card-inner"><p class="summary-label">Stock con alerta</p><p class="summary-value">'.e((string) $lowStockCount).'</p><p class="summary-note">Productos con stock bajo o agotado.</p></div></td>',
            '<td class="summary-card"><div class="summary-card-inner"><p class="summary-label">Vencimientos</p><p class="summary-value">'.e((string) $expirationCount).'</p><p class="summary-note">Lotes vencidos o proximos a vencer.</p></div></td>',
            '<td class="summary-card"><div class="summary-card-inner"><p class="summary-label">Lotes vencidos</p><p class="summary-value">'.e((string) $expiredCount).'</p><p class="summary-note">Casos que requieren revision inmediata.</p></div></td>',
            '</tr></table>',
        ];

        if ($lowStockItems !== []) {
            $html[] = '<div class="section">';
            $html[] = '<h2 class="section-header" style="color:#991b1b;">Stock bajo o agotado</h2>';
            $html[] = '<p class="section-copy">Estos productos ya estan por debajo del minimo definido por el comercio.</p>';
            $html[] = '<table class="data-table desktop-table">';
            $html[] = '<thead><tr><th align="left">Producto</th><th align="left">Estado</th><th align="right">Actual</th><th align="right">Minimo</th></tr></thead><tbody>';

            foreach ($lowStockItems as $item) {
                $statusLabel = $item['status'] === 'out_of_stock' ? 'Agotado' : 'Bajo stock';
                $statusClass = $item['status'] === 'out_of_stock' ? 'pill pill-danger' : 'pill pill-warn';

                $html[] = '<tr>'
                    .'<td><strong>'.e((string) $item['product_name']).'</strong></td>'
                    .'<td><span class="'.$statusClass.'">'.e($statusLabel).'</span></td>'
                    .'<td align="right">'.e((string) $item['stock']).'</td>'
                    .'<td align="right">'.e((string) $item['min_stock']).'</td>'
                    .'</tr>';
            }

            $html[] = '</tbody></table></div>';
            $html[] = '<div class="mobile-cards">';

            foreach ($lowStockItems as $item) {
                $statusLabel = $item['status'] === 'out_of_stock' ? 'Agotado' : 'Bajo stock';
                $statusClass = $item['status'] === 'out_of_stock' ? 'pill pill-danger' : 'pill pill-warn';

                $html[] = '<div class="mobile-card">'
                    .'<p class="mobile-card-title">'.e((string) $item['product_name']).'</p>'
                    .'<div class="mobile-card-row"><span class="mobile-card-label">Estado</span><span class="'.$statusClass.'">'.e($statusLabel).'</span></div>'
                    .'<div class="mobile-card-row"><span class="mobile-card-label">Stock actual</span><span>'.e((string) $item['stock']).'</span></div>'
                    .'<div class="mobile-card-row"><span class="mobile-card-label">Stock minimo</span><span>'.e((string) $item['min_stock']).'</span></div>'
                    .'</div>';
            }

            $html[] = '</div>';
        }

        if ($expirationItems !== []) {
            $html[] = '<div class="section">';
            $html[] = '<h2 class="section-header" style="color:#92400e;">Vencimientos</h2>';
            $html[] = '<p class="section-copy">Estos lotes necesitan control para evitar perdidas o ventas con producto vencido.</p>';
            $html[] = '<table class="data-table desktop-table">';
            $html[] = '<thead><tr><th align="left">Producto</th><th align="left">Lote</th><th align="left">Estado</th><th align="right">Dias</th></tr></thead><tbody>';

            foreach ($expirationItems as $item) {
                $daysLabel = ($item['status'] ?? '') === 'expired'
                    ? 'Vencido'
                    : (string) ($item['days_remaining'] ?? '-');
                $statusLabel = ($item['status'] ?? '') === 'expired' ? 'Vencido' : 'Proximo a vencer';
                $statusClass = ($item['status'] ?? '') === 'expired' ? 'pill pill-danger' : 'pill pill-warn';

                $html[] = '<tr>'
                    .'<td><strong>'.e((string) $item['product_name']).'</strong><div class="muted">Vence '.e((string) ($item['expires_at'] ?? '-')).'</div></td>'
                    .'<td>'.e((string) ($item['batch_code'] ?: '-')).'</td>'
                    .'<td><span class="'.$statusClass.'">'.e($statusLabel).'</span></td>'
                    .'<td align="right">'.e($daysLabel).'</td>'
                    .'</tr>';
            }

            $html[] = '</tbody></table>';
            $html[] = '<div class="mobile-cards">';

            foreach ($expirationItems as $item) {
                $daysLabel = ($item['status'] ?? '') === 'expired'
                    ? 'Vencido'
                    : (string) ($item['days_remaining'] ?? '-');
                $statusLabel = ($item['status'] ?? '') === 'expired' ? 'Vencido' : 'Proximo a vencer';
                $statusClass = ($item['status'] ?? '') === 'expired' ? 'pill pill-danger' : 'pill pill-warn';

                $html[] = '<div class="mobile-card">'
                    .'<p class="mobile-card-title">'.e((string) $item['product_name']).'</p>'
                    .'<p class="mobile-card-meta">Lote '.e((string) ($item['batch_code'] ?: '-')).' - vence '.e((string) ($item['expires_at'] ?? '-')).'</p>'
                    .'<div class="mobile-card-row"><span class="mobile-card-label">Estado</span><span class="'.$statusClass.'">'.e($statusLabel).'</span></div>'
                    .'<div class="mobile-card-row"><span class="mobile-card-label">Dias</span><span>'.e($daysLabel).'</span></div>'
                    .'</div>';
            }

            $html[] = '</div></div>';
        }

        $html[] = '<div class="cta">';
        $html[] = '<p class="cta-title">Acciones sugeridas</p>';
        $html[] = '<p class="cta-copy">Revisa el dashboard para el resumen general: <a class="cta-link" href="'.$dashboardUrl.'">'.$dashboardUrl.'</a></p>';
        $html[] = '<p class="cta-copy" style="margin-top:8px;">Consulta y ajusta productos desde: <a class="cta-link" href="'.$productsUrl.'">'.$productsUrl.'</a></p>';
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '<div class="footer">Este mensaje se genero automaticamente porque el comercio tiene alertas operativas activas en ComerStock.</div>';
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</body></html>';

        return implode('', $html);
    }
}
