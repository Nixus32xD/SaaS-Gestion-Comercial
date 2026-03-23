<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CommercialGuideController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/CommercialGuide/Index', [
            'quick_rules' => [
                'Los planes de implementacion definen el nivel de puesta en marcha y la cantidad inicial de productos.',
                'La carga adicional sirve para ampliaciones posteriores o ajustes puntuales del catalogo.',
                'El mantenimiento mensual acompana al comercio una vez implementado el sistema.',
            ],
            'sections' => [
                [
                    'title' => 'Planes de implementacion',
                    'description' => 'Se venden segun el tamano inicial del catalogo y el nivel de acompanamiento que necesita el comercio.',
                    'items' => [
                        [
                            'title' => 'Plan Express',
                            'ideal_for' => 'Comercios chicos o de arranque rapido.',
                            'summary' => 'Configuracion base, capacitacion breve y hasta 30 productos incluidos.',
                            'highlights' => [
                                'Sirve para salir a operar con una estructura simple.',
                                'Es el punto de entrada para negocios con catalogo chico.',
                                'Si despues crece, puede sumar carga adicional.',
                            ],
                        ],
                        [
                            'title' => 'Plan Esencial',
                            'ideal_for' => 'La mayoria de los comercios minoristas.',
                            'summary' => 'Instalacion, configuracion inicial, capacitacion completa y hasta 100 productos.',
                            'highlights' => [
                                'Es la opcion recomendada para una puesta en marcha ordenada.',
                                'Tiene mejor profundidad de arranque que Express.',
                                'Equilibra alcance, acompanamiento y costo.',
                            ],
                        ],
                        [
                            'title' => 'Plan Plus',
                            'ideal_for' => 'Comercios con catalogo amplio o una operatoria mas exigente.',
                            'summary' => 'Implementacion mas completa, capacitacion por sectores y hasta 250 productos.',
                            'highlights' => [
                                'No vende solo mas productos: vende mejor implementacion.',
                                'Conviene cuando el negocio ya arranca grande o con mas complejidad.',
                                'Protege mejor la puesta en marcha desde el inicio.',
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Carga de productos',
                    'description' => 'Es un servicio adicional. No reemplaza el plan inicial, sino que lo complementa.',
                    'items' => [
                        [
                            'title' => 'Carga adicional por unidad',
                            'ideal_for' => 'Correcciones o ampliaciones puntuales.',
                            'summary' => 'Se cobra por producto cuando el comercio necesita sumar algunos articulos extra.',
                            'highlights' => [
                                'Aplica fuera del paquete inicial contratado.',
                                'Sirve para pequenos ajustes sin ir a un pack completo.',
                            ],
                        ],
                        [
                            'title' => 'Pack 100 o 250 adicionales',
                            'ideal_for' => 'Ampliaciones posteriores mas grandes.',
                            'summary' => 'Se usa cuando el comercio ya implementado necesita sumar bastante catalogo.',
                            'highlights' => [
                                'Es para productos adicionales, no para reemplazar Express, Esencial o Plus.',
                                'Se puede vender despues del plan inicial si el negocio crece.',
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Mantenimiento mensual',
                    'description' => 'Es el servicio de continuidad una vez que el sistema ya esta implementado.',
                    'items' => [
                        [
                            'title' => 'Plan Basico',
                            'ideal_for' => 'Comercios que necesitan soporte continuo simple.',
                            'summary' => 'Incluye soporte, correccion de errores y actualizaciones menores.',
                            'highlights' => [
                                'Mantiene el sistema estable.',
                                'Es el piso de acompanamiento post implementacion.',
                            ],
                        ],
                        [
                            'title' => 'Plan Operativo y Prioritario',
                            'ideal_for' => 'Comercios con mas uso o necesidad de respuesta mas cercana.',
                            'summary' => 'Suman prioridad, seguimiento y una mejor capacidad para ajustes menores.',
                            'highlights' => [
                                'No es desarrollo a medida libre.',
                                'Sirve para sostener la operacion con mas cercania.',
                            ],
                        ],
                    ],
                ],
            ],
            'internal_checklists' => [
                [
                    'title' => 'Checklist interno del Plan Plus',
                    'description' => 'Paso a paso sugerido para ejecutar una implementacion Plus con criterio uniforme.',
                    'items' => [
                        'Relevar cantidad estimada de productos, responsables y forma de trabajo del comercio.',
                        'Confirmar si van a usar control de vencimientos, lotes, sectores de venta o destinos de cobro.',
                        'Crear el comercio, usuario admin y configuracion inicial del sistema.',
                        'Preparar categorias base y estructura inicial del catalogo.',
                        'Cargar o coordinar hasta 250 productos iniciales con sus datos operativos.',
                        'Definir stock inicial, stock minimo y datos comerciales por producto.',
                        'Configurar proveedores clave y validar el flujo de compras.',
                        'Probar compras, ventas y ajuste de stock con ejemplos reales del negocio.',
                        'Configurar notificaciones por mail y destinatarios principales.',
                        'Dar capacitacion por sectores o por roles segun como trabaje el comercio.',
                        'Hacer una revision final de salida a operacion con el cliente.',
                        'Acompanhar los primeros dias para resolver dudas o ajustes menores de arranque.',
                    ],
                ],
                [
                    'title' => 'Que incluye cada mantenimiento',
                    'description' => 'Referencia interna para saber que tareas deberias absorber en cada nivel.',
                    'items' => [
                        'Basico: responder consultas, corregir errores, aplicar ajustes menores simples y mantener la app estable.',
                        'Operativo: todo lo del basico, mas seguimiento mas frecuente, revision operativa mensual y prioridad media.',
                        'Prioritario: todo lo del operativo, mas prioridad alta, seguimiento cercano y una bolsa mensual para pequenos ajustes.',
                    ],
                ],
            ],
            'whatsapp_templates' => [
                [
                    'key' => 'general',
                    'title' => 'Respuesta general',
                    'description' => 'Cuando preguntan que incluye el sistema.',
                    'message' => "Hola, te cuento rapido. El sistema te permite ordenar la operacion diaria del comercio con ventas, compras, stock, productos, proveedores, vencimientos y alertas por mail. La idea es dejarte una base simple para operar y despues acompanarte segun lo que necesites.",
                ],
                [
                    'key' => 'plans',
                    'title' => 'Como explicar los planes',
                    'description' => 'Cuando preguntan que hace cada plan.',
                    'message' => "Los planes de implementacion definen como arranca tu comercio en el sistema. Express incluye hasta 30 productos y una puesta en marcha base. Esencial incluye hasta 100 productos y una implementacion mas completa, por eso suele ser el recomendado. Plus incluye hasta 250 productos y esta pensado para negocios con mas catalogo o una operatoria mas exigente desde el inicio.",
                ],
                [
                    'key' => 'catalog',
                    'title' => 'Como explicar la carga adicional',
                    'description' => 'Cuando preguntan por carga por unidad o packs.',
                    'message' => "La carga adicional no reemplaza el plan inicial. Sirve para sumar productos extra fuera del paquete incluido o para ampliaciones posteriores del catalogo. Si arrancas con un plan y despues creces, ahi podes sumar carga por unidad o por packs.",
                ],
                [
                    'key' => 'maintenance',
                    'title' => 'Como explicar el mantenimiento',
                    'description' => 'Cuando preguntan por el abono mensual.',
                    'message' => "El mantenimiento mensual es para acompanarte una vez que el sistema ya esta implementado. Segun el plan incluye soporte, correccion de errores, actualizaciones menores y distintos niveles de seguimiento. La implementacion te deja el sistema listo para usar; el mantenimiento lo sostiene en el tiempo.",
                ],
                [
                    'key' => 'express_vs_plus',
                    'title' => 'Cuando comparan Express + carga vs Plus',
                    'description' => 'Para responder la objecion mas comun.',
                    'message' => "Express mas carga adicional puede servir si queres arrancar simple y despues ampliar catalogo. Pero el Plan Plus no vende solo mas productos: tambien incluye una implementacion mas completa, mejor capacitacion y una puesta en marcha mas profunda. Por eso una cosa suma productos y la otra mejora el nivel de arranque.",
                ],
                [
                    'key' => 'plus_execution',
                    'title' => 'Como explicar que hago en un Plan Plus',
                    'description' => 'Para cuando te preguntan concretamente que incluye tu trabajo.',
                    'message' => "Si elegis el Plan Plus, yo me encargo de una puesta en marcha mas completa: relevamiento inicial, configuracion del sistema, carga de hasta 250 productos, ajustes operativos, capacitacion por sectores y acompanamiento de arranque para que el comercio quede listo para trabajar.",
                ],
                [
                    'key' => 'maintenance_scope',
                    'title' => 'Como explicar que hago en cada mantenimiento',
                    'description' => 'Respuesta corta para diferenciar basico, operativo y prioritario.',
                    'message' => "El mantenimiento cambia segun el nivel de acompanamiento. En el Basico me ocupo de soporte, correccion de errores y estabilidad general. En el Operativo sumo mas seguimiento y prioridad media. En el Prioritario agrego acompanamiento mas cercano, prioridad alta y una capacidad mayor para ajustes menores en el dia a dia.",
                ],
            ],
        ]);
    }
}
