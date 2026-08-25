# ✅ ARCHIVOS ACTUALIZADOS - LISTO PARA HOSTINGER

## 📂 Carpeta del Escritorio actualizada

**Ubicación:** `/Users/oscarnicolasstella/Desktop/backend/`

---

## ✅ ARCHIVOS MODIFICADOS (ya están en el Escritorio)

1. ✏️ `register_push_token.php` - Actualizado
2. ➕ `send_fcm_notification.php` - Nuevo (agregado)
3. ✏️ `enviar_mensaje.php` - Actualizado
4. ✏️ `solicitudes_responder.php` - Actualizado

---

## 🚀 PRÓXIMOS PASOS

### ✅ PASO 1: Base de datos (YA HECHO)
~~Ejecutar create_push_tokens_table.sql~~ ✅

---

### ⏳ PASO 2: Subir carpeta backend a Hostinger

**Acción:**
1. Abre File Manager en cPanel de Hostinger
2. Ve a `public_html/`
3. **Elimina** la carpeta `backend/` vieja (o renómbrala a `backend_old/`)
4. **Sube** la carpeta `backend/` del Escritorio completa

**Resultado:** Todo el backend quedará actualizado con soporte para FCM

---

### ⏳ PASO 3: Descargar firebase-service-account.json

**Link directo:** https://console.firebase.google.com/project/ubertec-a6860/settings/serviceaccounts/adminsdk

**Pasos:**
1. Abre el link
2. Click en **"Generate new private key"**
3. Click en **"Generate key"** (confirmar)
4. Se descargará un archivo JSON
5. **Renómbralo a:** `firebase-service-account.json`

**Destino en Hostinger:**
- Sube el archivo a: `public_html/backend/firebase-service-account.json`

---

## 📋 CHECKLIST FINAL

```
[✅] 1. Ejecutar SQL en phpMyAdmin (YA HECHO)
[✅] 2. Archivos PHP actualizados en Escritorio (YA HECHO)
[ x] 3. Subir carpeta backend/ completa a Hostinger
[ x] 4. Descargar firebase-service-account.json
[ x] 5. Subir firebase-service-account.json a backend/
```

---

## 🎯 RESUMEN

**Ya hecho:**
- ✅ SQL ejecutado
- ✅ Archivos PHP actualizados en Escritorio

**Falta:**
- ⏳ Subir carpeta backend/ a Hostinger
- ⏳ Descargar y subir firebase-service-account.json

¡Solo 2 pasos más! 🚀
