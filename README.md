# Instalación del Proyecto

## Instalar las dependencias de Composer

```bash
composer install
```

## Crear el archivo de configuración

```bash
cp .env.example .env
```

## Configurar el archivo `.env`

Completa los datos necesarios, como:

- Nombre de la aplicación
- Conexión a la base de datos
- Usuario y contraseña de la base de datos
- Otras variables de entorno requeridas

## Generar la clave de la aplicación

```bash
php artisan key:generate
```

## Ejecutar las migraciones

```bash
php artisan migrate
```

# Prueba Técnica – API REST de Ventas en Laravel

## Objetivo

Desarrollar una API REST en Laravel para gestionar un flujo básico de ventas.

La prueba evaluará:

- Uso de relaciones entre modelos.
- Validación de datos.
- Manejo de transacciones.
- Control de stock.
- Lógica de negocio.
- Respuestas JSON y códigos HTTP.
- Pruebas mediante Postman u otro cliente HTTP.

---

## Estado inicial del proyecto

El proyecto ya incluye:

- Laravel configurado como API.
- Migraciones listas.
- Seeders con productos cargados.
- Modelos base: `Product`, `Sale` y `SaleDetail`.

---

## Fuera de alcance

No se debe implementar mantenimiento de productos.

Por lo tanto, no corresponde:

- Crear productos.
- Modificar productos.
- Eliminar productos.

Solo se deben utilizar los productos existentes.

---

## Requerimientos

### 1. Listar productos

**Endpoint**

```http
GET /api/products
```

Debe retornar:

- `id`
- `name`
- `price`
- `stock`

**Ejemplo**

```json
[
  {
    "id": 1,
    "name": "Laptop Lenovo",
    "price": 7500,
    "stock": 10
  }
]
```

---

### 2. Crear una venta

Usar el usuario estático:

```text
user_id = 1
```

**Endpoint**

```http
POST /api/sales
```

**Request**

```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 3,
      "quantity": 1
    }
  ]
}
```

La operación debe:

1. Validar que exista al menos un producto.
2. Validar que cada producto exista.
3. Validar que la cantidad sea mayor que cero.
4. Verificar que exista stock suficiente.
5. Crear la venta.
6. Crear los detalles de la venta.
7. Calcular el subtotal de cada producto.
8. Calcular el total de la venta.
9. Descontar el stock vendido.

Los precios deben obtenerse desde la base de datos y no desde el request.

La creación de la venta, sus detalles y el descuento de stock deben ejecutarse dentro de una transacción.

**Cálculos**

```text
subtotal = price × quantity
total = suma de subtotales
```

**Ejemplo de respuesta**

```json
{
  "message": "Venta creada correctamente",
  "sale_id": 1,
  "total": 16500
}
```

Si no existe stock suficiente, la venta no debe registrarse parcialmente.

---

### 3. Listar ventas

**Endpoint**

```http
GET /api/sales
```

Debe retornar:

- `id`
- `total`
- `created_at`
- Detalle de productos vendidos

**Ejemplo**

```json
[
  {
    "id": 1,
    "total": 16500,
    "created_at": "2026-01-21",
    "details": [
      {
        "product": "Laptop Lenovo",
        "quantity": 2,
        "subtotal": 15000
      }
    ]
  }
]
```

---

### 4. Eliminar una venta

**Endpoint**

```http
DELETE /api/sales/{id}
```

Debe eliminar la venta seleccionada.

**Ejemplo de respuesta**

```json
{
  "message": "Venta eliminada correctamente"
}
```

Si la venta no existe, debe devolverse una respuesta de error adecuada.

---

## Reglas de negocio

- Una venta debe contener al menos un producto.
- No se permiten cantidades iguales o menores que cero.
- No se puede vender más stock del disponible.
- El precio debe obtenerse desde el producto registrado.
- El subtotal se calcula por producto.
- El total corresponde a la suma de los subtotales.
- La creación debe ejecutarse dentro de una transacción.
- Ante cualquier error, no deben quedar datos parciales.
- Las respuestas deben estar en formato JSON.

---

## Resultado esperado

Al finalizar la prueba, la API debe permitir:

- Consultar productos existentes.
- Registrar una venta con sus detalles.
- Calcular subtotales y total.
- Descontar stock.
- Consultar ventas.
- Eliminar ventas.

---

## Características Opcionales

### Descuento por producto

**Esta funcionalidad es opcional y no es requerida para completar la prueba.**

Se puede implementar un sistema de descuentos aplicados a nivel de producto dentro de una venta.

**Cambios en el request de crear venta**

```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "discount_percent": 10
    },
    {
      "product_id": 3,
      "quantity": 1,
      "discount_percent": 5
    }
  ]
}
```

**Cálculos con descuento**

```text
subtotal = price × quantity
discount_amount = subtotal × (discount_percent / 100)
subtotal_with_discount = subtotal - discount_amount
total = suma de subtotales_with_discount
```

**Cambios en la respuesta de listar ventas**

```json
[
  {
    "id": 1,
    "total": 15000,
    "total_discount": 1500,
    "created_at": "2026-01-21",
    "details": [
      {
        "product": "Laptop Lenovo",
        "quantity": 2,
        "subtotal": 15000,
        "discount_percent": 10,
        "discount_amount": 1500,
        "subtotal_with_discount": 13500
      }
    ]
  }
]
```

**Migración requerida**

Se debe crear las migraciones requeridas para aplicar los requerimientos solicitados

**Reglas de negocio adicionales (si se implementa)**

- El descuento es opcional (por defecto 0%).
- El descuento debe ser un valor entre 0 y 10.
- El cálculo del descuento debe realizarse dentro de la transacción.
- El total debe incluir la suma de los subtotales con descuento.
