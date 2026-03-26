<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessMaintenanceReminderMail extends Mailable
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
        $summary = $this->payload['summary'] ?? [];
        $generatedAt = e((string) ($this->payload['generated_at'] ?? now()->format('Y-m-d H:i')));
        $dashboardUrl = e(url('/dashboard'));
        $planTitle = e((string) ($summary['plan_title'] ?? 'Plan mensual'));
        $amountLabel = e((string) ($summary['amount_label'] ?? '-'));
        $endsAtLabel = e((string) ($summary['ends_at_label'] ?? '-'));
        $graceEndsAtLabel = e((string) ($summary['grace_ends_at_label'] ?? '-'));
        $daysToDue = (int) ($summary['days_to_due'] ?? 0);

        $html = [
            '<!DOCTYPE html>',
            '<html lang="es">',
            '<head>',
            '<meta charset="UTF-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
            '<title>'.e($this->subjectLine).'</title>',
            '<style>',
            'body{margin:0;padding:0;background:#e2e8f0;font-family:Arial,sans-serif;color:#0f172a;}',
            '.wrapper{width:100%;background:#e2e8f0;padding:24px 12px;}',
            '.shell{width:100%;max-width:720px;margin:0 auto;background:#0f172a;border-radius:24px;overflow:hidden;}',
            '.hero{padding:32px 28px;background:linear-gradient(135deg,#164e63 0%,#0f172a 55%,#1e293b 100%);color:#f8fafc;}',
            '.hero-kicker{display:inline-block;padding:6px 10px;border-radius:999px;background:rgba(34,211,238,0.16);color:#a5f3fc;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;}',
            '.hero-title{margin:16px 0 10px;font-size:28px;line-height:1.2;font-weight:700;}',
            '.hero-copy{margin:0;color:#cbd5e1;font-size:15px;line-height:1.6;}',
            '.content{background:#ffffff;padding:28px;}',
            '.notice{border:1px solid #fcd34d;border-radius:18px;background:#fffbeb;padding:18px;color:#92400e;}',
            '.notice-title{margin:0 0 8px;font-size:20px;font-weight:700;color:#78350f;}',
            '.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px;}',
            '.card{border:1px solid #dbeafe;border-radius:16px;background:#f8fafc;padding:16px 14px;}',
            '.label{margin:0 0 8px;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;}',
            '.value{margin:0;color:#0f172a;font-size:24px;font-weight:700;line-height:1.2;}',
            '.copy{margin:18px 0 0;color:#334155;font-size:14px;line-height:1.65;}',
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
            '.grid{grid-template-columns:1fr;}',
            '}',
            '</style>',
            '</head>',
            '<body>',
            '<div class="wrapper">',
            '<div class="shell">',
            '<div class="hero">',
            '<span class="hero-kicker">ComerStock</span>',
            '<h1 class="hero-title">Recordatorio de mantenimiento para '.e($this->businessName).'</h1>',
            '<p class="hero-copy">Generado el '.$generatedAt.'. Este aviso se envia automaticamente 7 dias antes del vencimiento del abono mensual.</p>',
            '</div>',
            '<div class="content">',
            '<div class="notice">',
            '<p class="notice-title">Faltan '.e((string) $daysToDue).' dias para el vencimiento</p>',
            '<p style="margin:0;font-size:14px;line-height:1.6;">El comercio tiene cargado el '.$planTitle.' y el mantenimiento vence el '.$endsAtLabel.'. Para evitar interrupciones, conviene regularizar el pago antes de esa fecha.</p>',
            '</div>',
            '<div class="grid">',
            '<div class="card"><p class="label">Plan</p><p class="value">'.$planTitle.'</p></div>',
            '<div class="card"><p class="label">Importe mensual</p><p class="value">'.$amountLabel.'</p></div>',
            '<div class="card"><p class="label">Vence</p><p class="value">'.$endsAtLabel.'</p></div>',
            '</div>',
            '<p class="copy">Si el pago no se registra a tiempo, el comercio entra en gracia y luego puede quedar suspendido. La fecha limite de gracia cargada hoy es '.$graceEndsAtLabel.'.</p>',
            '<div class="cta">',
            '<p class="cta-title">Siguiente paso sugerido</p>',
            '<p class="cta-copy">Coordina el pago del mantenimiento y revisa el estado general del comercio desde: <a class="cta-link" href="'.$dashboardUrl.'">'.$dashboardUrl.'</a></p>',
            '</div>',
            '</div>',
            '<div class="footer">Este mensaje se genero automaticamente porque el comercio tiene activados los recordatorios de mantenimiento por vencer.</div>',
            '</div>',
            '</div>',
            '</body></html>',
        ];

        return implode('', $html);
    }
}
