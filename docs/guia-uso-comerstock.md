# Guia de uso de ComerStock

ComerStock es un sistema de gestion comercial para comercios chicos y medianos. Sirve para vender en mostrador, controlar stock, registrar compras, manejar clientes, cuentas corrientes, alertas operativas y, cuando el comercio lo tenga habilitado, emitir o seguir comprobantes fiscales mediante la API fiscal externa.

Esta guia esta dividida en dos partes:

- Uso diario del comercio: para clientes y usuarios que trabajan la caja, stock y compras.
- Panel administrador SaaS: para administrar comercios, planes, funcionalidades y configuracion fiscal.

## 1. Uso diario del comercio

### Ingreso al sistema

1. Entrar a la URL de la aplicacion.
2. Iniciar sesion con el usuario asignado.
3. Si el usuario pertenece a un comercio activo, el sistema abre el dashboard operativo.

Roles habituales:

- Admin del comercio: puede operar y administrar configuraciones del comercio.
- Staff: puede trabajar la operacion diaria, con permisos mas limitados.

### Dashboard

El dashboard muestra un resumen rapido del estado del comercio:

- ventas del dia;
- ventas del mes;
- compras recientes;
- stock bajo;
- productos mas vendidos;
- alertas de vencimientos;
- actividad operativa reciente.

Usarlo al inicio del dia para detectar faltantes, productos por vencer y estado general de ventas.

### Productos

Desde Productos se cargan y mantienen los articulos del comercio.

Datos recomendados:

- nombre claro;
- categoria;
- proveedor cuando aplique;
- codigo de barras o SKU;
- precio de venta;
- precio de costo;
- stock actual;
- stock minimo;
- tipo de unidad: unidad o peso;
- tratamiento de IVA y alicuota, si corresponde.

Buenas practicas:

- Usar codigo de barras cuando el producto lo tenga.
- Usar SKU interno para productos sin codigo.
- Cargar stock minimo para recibir alertas.
- Para productos por peso, revisar bien si se vende por kg o gramos.
- Mantener precio de costo actualizado para medir margen.

### Categorias y proveedores

Las categorias ayudan a ordenar productos y filtrar listados.

Ejemplos por rubro:

- Kiosco: Bebidas, Golosinas, Cigarrillos, Almacen.
- Ferreteria: Tornilleria, Herramientas, Electricidad, Pintura.
- Lubricentro: Aceites, Filtros, Aditivos, Servicios.
- Comercio general: Mostrador, Repuestos, Servicios, Insumos.

Los proveedores permiten registrar compras y saber de donde se repone cada producto.

### Compras

Las compras sirven para ingresar mercaderia y actualizar stock.

Flujo recomendado:

1. Ir a Compras.
2. Seleccionar proveedor.
3. Agregar productos existentes o cargar uno nuevo si no existe.
4. Indicar cantidad, costo y vencimiento/lote si corresponde.
5. Confirmar compra.

Impacto:

- aumenta stock;
- actualiza costo;
- registra movimiento;
- puede crear lotes y vencimientos.

### Ventas / POS

La pantalla de Nueva venta esta pensada para trabajar rapido en mostrador.

Flujo basico:

1. Buscar producto por nombre, SKU o codigo de barras.
2. Indicar cantidad.
3. Agregar al carrito.
4. Repetir con los productos necesarios.
5. Seleccionar cliente si la venta es fiada o parcial.
6. Elegir medio de pago.
7. Confirmar venta.

Medios de pago disponibles:

- efectivo;
- transferencia;
- QR;
- tarjeta debito;
- tarjeta credito.

En efectivo el sistema calcula monto recibido y vuelto. En transferencia, QR y tarjetas el cobro queda registrado manualmente y no calcula vuelto. Si el comercio usa configuracion avanzada de ventas, seleccionar tambien el destino de cobro: caja, banco, QR, Mercado Pago, posnet o terminal equivalente.

Atajos utiles:

- F2: enfocar buscador.
- F4: enfocar cantidad.
- Alt + A: agregar producto seleccionado.
- Ctrl + Enter: confirmar venta.
- Esc: limpiar busqueda.

### Venta rapida sin stock

Usar esta seccion para conceptos que se cobran pero no se quieren manejar como stock.

Ejemplos:

- producto suelto no cargado;
- mano de obra;
- servicio puntual;
- material fraccionado;
- aceite por litro;
- tornilleria suelta;
- ajuste o recargo operativo.

Como usarla:

1. Elegir una opcion rapida o escribir el detalle manual.
2. Cargar el monto.
3. Revisar IVA si corresponde.
4. Agregar al carrito.

Importante:

- No descuenta stock.
- Queda registrada en la venta.
- Puede tener IVA propio.
- Los admin del comercio pueden agregar opciones rapidas propias.

### Clientes y cuenta corriente

El modulo de clientes permite guardar datos de compradores y manejar deuda.

Usar cliente obligatorio cuando:

- la venta queda fiada;
- la venta tiene pago parcial;
- se necesita historial del comprador.

Desde la cuenta corriente se puede:

- ver deuda;
- registrar pagos;
- revisar movimientos;
- enviar recordatorios por WhatsApp o email cuando este configurado.

### Comprobantes adjuntos

En ventas se puede adjuntar un comprobante, como PDF o imagen.

Sirve para:

- guardar comprobantes de transferencia, QR o tarjeta;
- conservar respaldo de pagos;
- revisar operaciones despues.

### Facturacion electronica

Si el comercio tiene facturacion electronica habilitada, el sistema puede iniciar emision fiscal mediante una API fiscal externa.

El SaaS guarda:

- estado del comprobante;
- intentos de emision;
- CAE o CAEA cuando corresponda;
- observaciones o rechazos;
- PDF fiscal autorizado cuando este disponible.

Si una emision falla, revisar el detalle de la venta y usar las acciones disponibles para reintentar o conciliar.

## 2. Panel administrador SaaS

El panel administrador permite gestionar comercios, configuraciones comerciales, planes, pagos y funcionalidades.

### Comercios

Desde el panel de administracion se puede:

- crear comercios;
- editar datos generales;
- activar o desactivar acceso;
- revisar estado de mantenimiento;
- configurar funcionalidades.

Datos importantes:

- nombre del comercio;
- responsable;
- email;
- telefono;
- direccion;
- estado activo/inactivo.

### Usuarios del comercio

Cada comercio puede tener usuarios internos.

Roles:

- Admin: administra el comercio y configuraciones.
- Staff: opera funciones del dia a dia.

Buenas practicas:

- No compartir usuarios entre personas.
- Desactivar usuarios que ya no trabajan en el comercio.
- Mantener al menos un admin activo por comercio.

### Planes y pagos

El panel permite registrar:

- plan de implementacion;
- monto de implementacion;
- plan de mantenimiento;
- monto mensual;
- fecha de inicio;
- fecha de vencimiento;
- pagos manuales;
- notas internas.

Si el mantenimiento vence y pasa el periodo de gracia, el acceso del comercio puede quedar bloqueado.

### Funcionalidades por comercio

Se pueden habilitar o deshabilitar funciones por comercio.

Ejemplos:

- configuracion avanzada de ventas;
- catalogo global de productos;
- facturacion electronica.

Esto permite adaptar el sistema a rubros distintos sin cargar pantallas innecesarias.

### Configuracion avanzada de ventas

Cuando esta activa, permite configurar:

- sectores o puntos de venta;
- destinos de cobro.

Ejemplos de sectores:

- Mostrador;
- Taller;
- Deposito;
- Reparto.

Ejemplos de destinos:

- Efectivo caja;
- Mercado Pago Mostrador;
- QR Mostrador;
- Posnet / terminal;
- Banco;
- Cuenta del taller.

### Configuracion fiscal

Para comercios con facturacion electronica:

- habilitar modulo fiscal;
- configurar CUIT;
- condicion frente al IVA;
- ambiente de API fiscal;
- punto de venta;
- tipo de comprobante;
- modo CAE/CAEA/automatico;
- actividades fiscales cuando aplique.

El SaaS no debe almacenar claves privadas fiscales locales. La generacion de CSR y carga de certificado se delega en la API fiscal externa.

### Notificaciones

El comercio puede configurar alertas operativas:

- stock bajo;
- vencimientos;
- mantenimiento proximo a vencer;
- destinatarios extra;
- ventana horaria;
- frecuencia minima entre envios.

Recomendacion:

- usar email del comercio para alertas generales;
- sumar emails internos si hay responsables de compras o administracion;
- evitar ventanas de envio fuera del horario laboral.

## 3. Checklist para entregar a un cliente

Antes de entregar el sistema a un comercio:

1. Crear comercio y usuario admin.
2. Confirmar que el comercio este activo.
3. Configurar plan y vencimiento de mantenimiento.
4. Crear categorias basicas del rubro.
5. Cargar proveedores principales.
6. Cargar productos iniciales o importar desde catalogo global si esta habilitado.
7. Revisar stock minimo y precios.
8. Configurar sectores/destinos de cobro si el comercio los usa.
9. Configurar opciones rapidas sin stock propias del rubro.
10. Configurar notificaciones.
11. Si usa facturacion electronica, validar CUIT, punto de venta y conexion con API fiscal.
12. Hacer una venta de prueba.
13. Hacer una compra de prueba.
14. Revisar dashboard, stock y cuenta corriente.

## 4. Checklist diario para el comercio

Al iniciar el dia:

1. Revisar dashboard.
2. Revisar stock bajo.
3. Revisar vencimientos.
4. Confirmar caja o medios de cobro.

Durante el dia:

1. Registrar ventas desde POS.
2. Usar venta rapida solo para conceptos sin stock.
3. Asociar cliente cuando sea fiado o pago parcial.
4. Cargar comprobante si hay transferencia, QR o tarjeta.

Al cerrar:

1. Revisar ventas del dia.
2. Revisar cobros pendientes.
3. Registrar compras o reposiciones.
4. Revisar alertas para el dia siguiente.
