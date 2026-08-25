# ✅ CAMBIOS EN EL BACKEND - Perfil Completo

## 📂 Archivos modificados/creados en Escritorio

**Ubicación:** `/Users/oscarnicolasstella/Desktop/backend/`

---

## 🔧 ARCHIVOS MODIFICADOS

### 1️⃣ **get_perfil.php** - ACTUALIZADO ✏️

📍 **Archivo:** [`/Desktop/backend/get_perfil.php`](file:///Users/oscarnicolasstella/Desktop/backend/get_perfil.php)

**Cambios:**
- ✅ Ahora devuelve **foto** del usuario/profesional
- ✅ Ahora devuelve **saldo** (estado de cuenta) para profesionales
- ✅ Ahora devuelve **casos_pendientes** y **valor_caso**
- ✅ Devuelve **todos los campos** del perfil (apellido, whatsapp, domicilio, etc.)

**Campos agregados para USUARIO:**
- `apellido`, `whatsapp`, `domicilio`, `casa`, `foto`

**Campos agregados para PROFESIONAL:**
- `apellido`, `whatsapp`, `direccion`, `experiencia`, `descripcion`, `foto`
- `saldo`, `casos_pendientes`, `valor_caso`, `categorias`

---

### 2️⃣ **editar_perfil.php** - NUEVO ➕

📍 **Archivo:** [`/Desktop/backend/editar_perfil.php`](file:///Users/oscarnicolasstella/Desktop/backend/editar_perfil.php)

**Funcionalidad:**
- ✅ Permite editar **todos los datos** del perfil
- ✅ Soporta **subida de foto** de perfil
- ✅ Valida que la foto sea imagen (JPG, PNG, WEBP)
- ✅ Valida tamaño máximo de 5MB
- ✅ **NO permite editar DNI** (como solicitaste)
- ✅ Verifica que el email no esté en uso por otro usuario

**Campos editables para USUARIO:**
- `nombre`, `apellido`, `email`, `whatsapp`, `domicilio`, `casa`, `foto`

**Campos editables para PROFESIONAL:**
- `nombre`, `apellido`, `email`, `whatsapp`, `direccion`, `experiencia`, `descripcion`, `foto`

---

## 📋 PRÓXIMOS PASOS

### ✅ Subir archivos a Hostinger

**Acción:**
1. Sube toda la carpeta `backend/` del Escritorio a Hostinger
2. Reemplaza `public_html/backend/`

**Archivos que se actualizarán:**
- ✏️ `get_perfil.php` (actualizado)
- ➕ `editar_perfil.php` (nuevo)
- ✏️ Todos los archivos anteriores de FCM

---

## 🧪 CÓMO USAR

### Obtener perfil completo:
```javascript
fetch('https://buscotec.click/backend/get_perfil.php', {
  credentials: 'include'
})
.then(r => r.json())
.then(data => {
  console.log(data.data.profesional.saldo); // Estado de cuenta
  console.log(data.data.profesional.foto); // Foto de perfil
});
```

### Editar perfil con foto:
```javascript
const formData = new FormData();
formData.append('nombre', 'Juan');
formData.append('apellido', 'Pérez');
formData.append('email', 'juan@example.com');
formData.append('whatsapp', '+5491234567890');
formData.append('foto', fileInput.files[0]); // Archivo de imagen

fetch('https://buscotec.click/backend/editar_perfil.php', {
  method: 'POST',
  credentials: 'include',
  body: formData
})
.then(r => r.json())
.then(data => console.log(data));
```

---

## ✅ CHECKLIST

```
[✅] 1. get_perfil.php actualizado (con saldo y foto)
[✅] 2. editar_perfil.php creado (con subida de foto)
[ ] 3. Subir carpeta backend/ completa a Hostinger
[ ] 4. Probar en la app que se vea el estado de cuenta
[ ] 5. Probar editar perfil con foto
```

---

## 🎯 RESUMEN

**Problema resuelto:**
- ✅ Ahora `get_perfil.php` devuelve el **estado de cuenta** (saldo)
- ✅ Ahora puedes **editar el perfil** completo con foto
- ✅ **NO se puede editar el DNI** (como solicitaste)

**Próximo paso:**
- Subir carpeta `backend/` completa a Hostinger

¿Listo para subir? 🚀
