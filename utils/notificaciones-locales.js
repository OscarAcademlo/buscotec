// utils/notificaciones-locales.js
// Sistema simple de notificaciones para desarrollo y producción

import { Alert, Vibration, Platform } from 'react-native';

let ultimoConteoMensajes = 0;
let callbackNuevoMensaje = null;

// Registrar callback para cuando lleguen nuevos mensajes
export function setCallbackNuevoMensaje(callback) {
    callbackNuevoMensaje = callback;
}

// Vibrar con un patrón más fuerte: [espera, vibra, espera, vibra]
export function mostrarNotificacionLocal(titulo, mensaje, onPress = null) {
    if (Platform.OS !== 'web') {
        Vibration.vibrate([0, 500, 200, 500]);
    }

    Alert.alert(
        titulo,
        mensaje,
        onPress ? [
            { text: 'Ver', onPress: onPress },
            { text: 'Cerrar', style: 'cancel' }
        ] : [{ text: 'OK' }]
    );
}

// Solicitar permisos (preparado para futuro con expo-notifications)
export async function solicitarPermisosNotificaciones() {
    console.log('📱 Notificaciones: Sistema activo (modo desarrollo)');
    return true;
}

// Obtener el conteo actual (útil para mostrar badge)
export function getConteoMensajes() {
    return ultimoConteoMensajes;
}

// Resetear conteo (cuando el usuario ve los mensajes)
export function resetearConteoMensajes() {
    ultimoConteoMensajes = 0;
}

// Polling mejorado: verificar mensajes nuevos cada X minutos
export function iniciarPollingMensajes(emailUsuario, intervaloMinutos = 3) {
    const intervaloMs = intervaloMinutos * 60 * 1000;

    const verificar = async () => {
        try {
            const response = await fetch('https://buscotec.click/backend/get_unread_count.php', {
                method: 'GET',
                credentials: 'include',
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });

            const data = await response.json();

            if (data.ok) {
                const nuevoConteo = parseInt(data.unread) || 0;

                // Actualizar siempre el callback para asegurar que la UI tenga el número correcto
                if (callbackNuevoMensaje) {
                    callbackNuevoMensaje(nuevoConteo, nuevoConteo > ultimoConteoMensajes ? (nuevoConteo - ultimoConteoMensajes) : 0);
                }

                // Si hay mensajes genuinamente nuevos, mostrar la alerta
                if (nuevoConteo > 0 && nuevoConteo > ultimoConteoMensajes) {
                    const nuevos = nuevoConteo - ultimoConteoMensajes;
                    mostrarNotificacionLocal(
                        '📬 BuscoTec',
                        `Tenés ${nuevos === 1 ? 'un mensaje nuevo' : `${nuevos} mensajes nuevos`}`
                    );
                }

                ultimoConteoMensajes = nuevoConteo;
            }
        } catch (error) {
            console.log('Error verificando mensajes:', error.message);
        }
    };

    // Verificar después de 1 segundo (dar tiempo al login)
    setTimeout(verificar, 1000);

    // Luego cada X minutos
    const intervalId = setInterval(verificar, intervaloMs);

    return intervalId;
}

// Verificar mensajes manualmente (pull-to-refresh, etc)
export async function verificarMensajesAhora() {
    try {
        const response = await fetch('https://buscotec.click/backend/get_unread_count.php', {
            method: 'GET',
            credentials: 'include',
            cache: 'no-store'
        });

        const data = await response.json();

        if (data.ok) {
            ultimoConteoMensajes = parseInt(data.unread) || 0;
            return ultimoConteoMensajes;
        }
    } catch (error) {
        console.log('Error verificando mensajes:', error);
    }
    return 0;
}

// Detener el polling
export function detenerPollingMensajes(intervalId) {
    if (intervalId) {
        clearInterval(intervalId);
        console.log('📱 Polling detenido');
    }
}
