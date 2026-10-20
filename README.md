# D&L Pijamas

Sitio web de catálogo y compras para D&L Pijamas. Incluye páginas de inicio,
catálogo con búsqueda y filtros, carrito, cálculo de envío y confirmación de
pedidos por WhatsApp. El envío gratis aplica desde 5 prendas o desde $200.000.

Los nombres, ilustraciones y precios del catálogo son de referencia; deben
actualizarse con el inventario real antes de publicar la tienda.

## Anuncios de nuevas colecciones

El sitio requiere Apache y PHP de XAMPP para publicar anuncios desde el panel.

1. Inicia Apache en el panel de control de XAMPP.
2. Abre `http://localhost/Pijamas%20D%26L/admin/setup.php` en este equipo para
   configurar el usuario y una contraseña de al menos 14 caracteres. La
   configuración inicial solo acepta conexiones locales.
3. Inicia sesión en `http://localhost/Pijamas%20D%26L/admin/` para redactar,
   publicar o desactivar el anuncio que se muestra en Inicio.

La carpeta `storage` debe permitir escritura a Apache y estar protegida por su
archivo `.htaccess`. Antes de publicar en internet, completa la configuración
inicial localmente y confirma que Apache permite las reglas de acceso de
`.htaccess`.