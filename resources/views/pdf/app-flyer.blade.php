<!doctype html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 24px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #122033;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.45;
        }
        .hero {
            background: #102a43;
            color: #ffffff;
            padding: 26px;
            border-radius: 12px;
        }
        .eyebrow {
            color: #67e8f9;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 30px;
            line-height: 1.08;
            margin: 0 0 10px;
        }
        .lead {
            font-size: 15px;
            color: #dbeafe;
            width: 88%;
            margin: 0;
        }
        .section {
            margin-top: 18px;
        }
        .grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px;
            margin-left: -12px;
        }
        .grid td {
            width: 50%;
            vertical-align: top;
        }
        .card {
            border: 1px solid #d9e2ec;
            border-radius: 10px;
            padding: 16px;
            min-height: 142px;
        }
        .card h2 {
            font-size: 17px;
            margin: 0 0 8px;
            color: #102a43;
        }
        .tag {
            display: inline-block;
            background: #e0f2fe;
            color: #075985;
            border-radius: 999px;
            padding: 4px 9px;
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        ul {
            margin: 8px 0 0;
            padding-left: 17px;
        }
        li {
            margin-bottom: 5px;
        }
        .highlight {
            background: #f8fafc;
            border-left: 5px solid #0891b2;
            padding: 15px 18px;
            border-radius: 8px;
            margin-top: 16px;
        }
        .highlight h2 {
            margin: 0 0 6px;
            font-size: 18px;
        }
        .metrics {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
            margin-top: 8px;
            margin-left: -10px;
        }
        .metrics td {
            width: 25%;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .metric {
            font-size: 18px;
            font-weight: bold;
            color: #0e7490;
        }
        .metric-label {
            font-size: 10px;
            color: #475569;
            margin-top: 3px;
        }
        .footer {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #d9e2ec;
            color: #475569;
            font-size: 11px;
        }
        .footer strong {
            color: #102a43;
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="eyebrow">Gestor Comercial SaaS + API Fiscal ARCA</div>
        <h1>Una plataforma para vender, controlar stock y facturar mejor.</h1>
        <p class="lead">
            Sistema web pensado para comercios chicos y medianos de Argentina:
            simple para usar en mostrador, ordenado para administrar y preparado para crecer.
        </p>
    </div>

    <table class="metrics">
        <tr>
            <td>
                <div class="metric">SaaS</div>
                <div class="metric-label">multi-comercio</div>
            </td>
            <td>
                <div class="metric">POS</div>
                <div class="metric-label">ventas rapidas</div>
            </td>
            <td>
                <div class="metric">Stock</div>
                <div class="metric-label">control diario</div>
            </td>
            <td>
                <div class="metric">ARCA</div>
                <div class="metric-label">CAE, CAEA y QR</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <table class="grid">
            <tr>
                <td>
                    <div class="card">
                        <span class="tag">Para el comercio</span>
                        <h2>Gestion operativa en un solo lugar</h2>
                        <ul>
                            <li>Productos, precios, stock minimo y movimientos.</li>
                            <li>Ventas con descuento, cobros y saldo pendiente.</li>
                            <li>Compras, proveedores y actualizacion de stock.</li>
                            <li>Clientes, cuentas corrientes y recordatorios.</li>
                            <li>Reportes simples para entender ventas y alertas.</li>
                        </ul>
                    </div>
                </td>
                <td>
                    <div class="card">
                        <span class="tag">Para administrar</span>
                        <h2>Modelo SaaS escalable</h2>
                        <ul>
                            <li>Cada comercio trabaja aislado por `business_id`.</li>
                            <li>Panel superadmin para altas y configuracion.</li>
                            <li>Usuarios internos con roles de admin y staff.</li>
                            <li>Arquitectura Laravel, Vue, Inertia y MySQL.</li>
                            <li>Base lista para sumar turnero o e-commerce.</li>
                        </ul>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="card">
                        <span class="tag">API fiscal</span>
                        <h2>Facturacion electronica integrada</h2>
                        <ul>
                            <li>Emision fiscal desde la venta registrada.</li>
                            <li>Soporte para CAE y CAEA.</li>
                            <li>Datos fiscales por comercio: CUIT, punto de venta y tipo de comprobante.</li>
                            <li>Manejo de errores, reintentos y conciliacion.</li>
                            <li>Compatible con produccion y homologacion.</li>
                        </ul>
                    </div>
                </td>
                <td>
                    <div class="card">
                        <span class="tag">Comprobante</span>
                        <h2>PDF fiscal con QR ARCA</h2>
                        <ul>
                            <li>PDF descargable para comprobantes autorizados.</li>
                            <li>QR oficial con payload ARCA version 1.</li>
                            <li>Incluye datos del emisor, receptor, items, total y autorizacion.</li>
                            <li>Validaciones para evitar comprobantes incompletos.</li>
                            <li>Listo para imprimir o enviar al cliente.</li>
                        </ul>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="highlight">
        <h2>Objetivo del proyecto</h2>
        <p>
            Resolver problemas reales de comercios: vender mas rapido, saber cuanto stock queda,
            controlar deudas de clientes, ordenar compras y emitir comprobantes fiscales sin salir del sistema.
        </p>
    </div>

    <div class="footer">
        <strong>Proyecto:</strong> Gestor Comercial SaaS<br>
        <strong>Stack:</strong> Laravel 12, PHP 8.3+, MySQL, Inertia.js, Vue 3, Tailwind CSS y Vite.<br>
        <strong>Enfoque:</strong> backend fuerte, interfaz rapida y separacion clara de logica de negocio.
    </div>
</body>
</html>
