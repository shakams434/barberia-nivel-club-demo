# Checklist de privacidad y cumplimiento

Este documento no reemplaza asesoría legal. Todo punto es obligatorio antes del lanzamiento comercial en Perú.

## Gobernanza

- [ ] Identificar titular y responsables del tratamiento.
- [ ] Inventariar datos, finalidad, fuente y acceso.
- [ ] Documentar base legal para fidelización y marketing por separado.
- [ ] Designar responsable interno de privacidad y seguridad.
- [ ] Mantener registro de incidentes y solicitudes.

## Transparencia y consentimiento

- [ ] Publicar política de privacidad accesible desde QR, local y comunicaciones.
- [ ] Validar legalmente textos exactos/versiones de consentimiento.
- [ ] Explicar finalidad, plazo, destinatarios y canal de contacto.
- [ ] Separar consentimiento de fidelización y promociones.
- [ ] No usar casillas premarcadas.
- [ ] Conservar evidencia de otorgamiento y revocación.
- [ ] Confirmar que `SALIR` cumple el mecanismo de opt-out.

## Derechos ARCO

- [ ] Procedimiento de acceso.
- [ ] Procedimiento de rectificación.
- [ ] Procedimiento de cancelación/supresión.
- [ ] Procedimiento de oposición.
- [ ] Plazos, identidad del solicitante y trazabilidad.
- [ ] Canal humano y respuesta documentada.
- [ ] Probar exportación y anonimización.

## Retención y eliminación

- [ ] Definir plazos por clientes, consentimientos, mensajes, campañas, tarjetas y auditoría.
- [ ] Eliminar tarjetas temporales no necesarias.
- [ ] Definir anonimización y excepciones de conservación.
- [ ] Documentar borrado en backups.
- [ ] Revisar datos inactivos periódicamente.

## Proveedores y transferencias

- [ ] Identificar Namecheap, Meta y correo como proveedores.
- [ ] Revisar contratos, anexos de tratamiento y subencargados.
- [ ] Evaluar transferencias internacionales.
- [ ] Informar transferencias y salvaguardas en la política.
- [ ] Restringir regiones y accesos cuando sea posible.

## Seguridad

- [ ] MFA en cPanel, Meta, repositorio y correo.
- [ ] Mínimo privilegio y cuentas individuales.
- [ ] Secretos fuera de Git y rotación documentada.
- [ ] HTTPS/HSTS, backups cifrados y prueba de restauración.
- [ ] Parches periódicos de PHP, Laravel, Composer y npm.
- [ ] Logs sin secretos ni teléfonos completos.
- [ ] Plan de respuesta y notificación de incidentes.
- [ ] Revisión de accesos y administradores.

## RNPD Perú

- [ ] Determinar con asesoría legal la obligación y alcance del registro.
- [ ] Registrar el banco de datos personales ante el Registro Nacional de Protección de Datos Personales (RNPD) del Perú antes de producción.
- [ ] Mantener la información del registro actualizada.
- [ ] Documentar flujos y medidas declaradas.

## Validación de salida

- [ ] Revisión legal peruana final.
- [ ] Prueba de consentimiento y `SALIR`.
- [ ] Prueba ARCO de extremo a extremo.
- [ ] Prueba de anonimización y backup.
- [ ] Aprobación escrita del responsable del negocio.

**Bloqueo de lanzamiento:** no pasar a producción comercial hasta completar la política, los consentimientos, los derechos ARCO, la retención, las transferencias, los contratos, la seguridad, la validación legal y el registro aplicable ante el RNPD.
