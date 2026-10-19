# GUÍA DE INTEGRACIÓN DE PASARELAS: CULQI & NIUBIZ (PAGOEFECTIVO)

Esta guía explica la arquitectura de pagos en **SYS-COPM**, el uso de tarjetas de prueba en desarrollo/staging y los pasos para activar Niubiz.

---

## 1. Integración Culqi (Pasarela Principal)

### Variables en `.env`:
```env
PAGOS_PASARELA=culqi
CULQI_PUBLIC_KEY=pk_test_XXXXXXXXXXXXXXXX
CULQI_SECRET_KEY=sk_test_XXXXXXXXXXXXXXXX
CULQI_WEBHOOK_SECRET=whsec_XXXXXXXXXXXXXXXX
```

### Tarjetas de Prueba Oficiales de Culqi (Moneda PEN):

| Concepto / Resultado | Número de Tarjeta | CVV | Expira | Resultado Esperado |
|----------------------|-------------------|-----|--------|---------------------|
| **Venta Exitosa (Sin 3DS)** | `4111 1111 1111 1111` | `123` | Fecha Futura | Pago confirmado, venta creada |
| **Fondos Insuficientes** | `4111 1111 1111 1001` | `123` | Fecha Futura | Error `Fondos insuficientes` |
| **Tarjeta Denegada / Bloqueada** | `4111 1111 1111 1002` | `123` | Fecha Futura | Error `Tarjeta denegada` |
| **3D Secure Requerido** | `4000 0000 0000 0002` | `123` | Fecha Futura | Redirección de autenticación OTP |

---

## 2. Integración Niubiz (VisaNet Perú)

La clase [`App\Services\Pagos\NiubizPasarela`](file:///c:/Users/MARCO/Desktop/SYS-COPM/app/Services/Pagos/NiubizPasarela.php) implementa la interfaz `PasarelaPago`. Para conectarla a los servidores de producción de Niubiz:

1. **Obtención de Token de Sesión:**
   - Realizar petición `POST https://apisandbox.vnforapps.com/api.security/v1/security` con credenciales HTTP Basic Auth para generar el `accessToken`.
   - Solicitar `sessionToken` con el monto de la compra.
2. **Formulario Web Checkout:**
   - Cargar el script oficial de Niubiz `https://static-content-qas.vnforapps.com/v2/js/checkout.js` con el `sessionToken`.
3. **Autorización del Cargo:**
   - Niubiz devuelve un `transactionToken` que el backend envía a `/api.authorization/v3/authorization/ecommerce/{merchantId}` para concretar la venta.
