// utils/push-notifications.js — Sistema de notificaciones push con Expo

import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import { Platform } from 'react-native';

/**
 * Configurar cómo se muestran las notificaciones cuando la app está en primer plano
 */
if (Constants.appOwnership !== 'expo') {
    Notifications.setNotificationHandler({
        handleNotification: async () => ({
            shouldShowAlert: true,
            shouldPlaySound: true,
            shouldSetBadge: true,
        }),
    });
}

/**
 * Registrar el dispositivo para notificaciones push y obtener el token
 * @returns {Promise<string|null>} Token de Expo Push o null si falla
 */
export async function registerForPushNotificationsAsync() {
    let token = null;

    // Configurar canal de notificaciones para Android
    if (Platform.OS === 'android') {
        await Notifications.setNotificationChannelAsync('default', {
            name: 'Mensajes de BuscoTec',
            importance: Notifications.AndroidImportance.MAX,
            vibrationPattern: [0, 250, 250, 250],
            lightColor: '#1877F2',
            sound: 'default',
            enableVibrate: true,
        });
    }

    // Verificar que sea un dispositivo real y no estemos en Expo Go (SDK 53+)
    if (!Device.isDevice) {
        // console.warn('⚠️ Las notificaciones push solo funcionan en dispositivos físicos');
        return null;
    }

    // Evitar error en Expo Go (SDK 53+)
    if (Constants.appOwnership === 'expo' || Constants.executionEnvironment === 'storeClient') {
        // console.log('ℹ️ Notificaciones remotas omitidas en Expo Go.');
        return null;
    }

    // Solicitar permisos
    const { status: existingStatus } = await Notifications.getPermissionsAsync();
    let finalStatus = existingStatus;

    if (existingStatus !== 'granted') {
        const { status } = await Notifications.requestPermissionsAsync();
        finalStatus = status;
    }

    if (finalStatus !== 'granted') {
        // console.log('❌ Permisos de notificación denegados');
        return null;
    }

    try {
        // Obtener el token de Expo Push
        const tokenData = await Notifications.getExpoPushTokenAsync({
            projectId: Constants.expoConfig?.extra?.eas?.projectId || 'tu-project-id'
        });

        token = tokenData.data;
        console.log('✅ Token de notificación obtenido:', token);
    } catch (error) {
        // console.log('❌ Error obteniendo token push:', error.message);
    }

    return token;
}

/**
 * Enviar el token al backend para guardarlo
 * @param {string} token - Token de Expo Push
 * @param {string} userEmail - Email del usuario
 * @returns {Promise<boolean>} true si se guardó correctamente
 */
export async function enviarTokenAlBackend(token, userEmail) {
    if (!token || !userEmail) {
        // console.warn('⚠️ Token o email vacío, no se puede guardar');
        return false;
    }

    try {
        const response = await fetch('https://buscotec.click/backend/guardar_token_push.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                token: token,
                email: userEmail,
                platform: Platform.OS,
                device_name: Device.modelName || 'Unknown',
                os_version: Device.osVersion || 'Unknown'
            })
        });

        const text = await response.text();
        if (!text) {
            console.log('✅ Token enviado (respuesta vacía)');
            return true;
        }

        try {
            const result = JSON.parse(text);
            if (result.ok) {
                console.log('✅ Token guardado en el backend');
                return true;
            } else {
                console.error('❌ Error del servidor:', result.error);
                return false;
            }
        } catch (e) {
            console.log('✅ Token enviado (respuesta no-JSON):', text);
            return true;
        }
    } catch (error) {
        console.error('❌ Error enviando token al backend:', error);
        return false;
    }
}

/**
 * Configurar listeners para notificaciones
 * @param {object} navigation - Objeto de navegación de React Navigation
 * @returns {function} Función para limpiar los listeners
 */
export function setupNotificationListeners(navigation) {
    // Listener cuando llega una notificación y la app está abierta
    const notificationListener = Notifications.addNotificationReceivedListener(notification => {
        console.log('🔔 Notificación recibida:', notification.request.content);

        // Puedes mostrar un toast o actualizar el badge aquí
        const { title, body } = notification.request.content;
        console.log(`📬 ${title}: ${body}`);
    });

    // Listener cuando el usuario toca una notificación
    const responseListener = Notifications.addNotificationResponseReceivedListener(response => {
        console.log('👆 Usuario tocó la notificación');

        const data = response.notification.request.content.data;

        // Navegar según el tipo de notificación
        if (data.tipo === 'mensaje') {
            // Navegar a mensajes
            if (data.conversacion_id) {
                navigation.navigate('DetalleMensaje', {
                    conversacionId: data.conversacion_id,
                    remitente: data.remitente
                });
            } else {
                navigation.navigate('Mensajes');
            }
        } else if (data.tipo === 'solicitud') {
            // Navegar a solicitudes/estado de cuenta
            navigation.navigate('EstadoCuenta');
        } else if (data.tipo === 'evaluacion') {
            // Navegar a perfil del profesional
            if (data.profesional_id) {
                navigation.navigate('PerfilProfesional', {
                    profesionalId: data.profesional_id
                });
            }
        } else {
            // Navegación por defecto
            navigation.navigate('Home');
        }
    });

    // Función de limpieza
    return () => {
        Notifications.removeNotificationSubscription(notificationListener);
        Notifications.removeNotificationSubscription(responseListener);
    };
}

/**
 * Configurar badge de notificaciones no leídas
 * @param {number} count - Número de notificaciones no leídas
 */
export async function setBadgeCount(count) {
    await Notifications.setBadgeCountAsync(count);
}

/**
 * Limpiar todas las notificaciones
 */
export async function clearAllNotifications() {
    await Notifications.dismissAllNotificationsAsync();
}

/**
 * Cancelar todas las notificaciones programadas
 */
export async function cancelAllScheduledNotifications() {
    await Notifications.cancelAllScheduledNotificationsAsync();
}

/**
 * Programar una notificación local (para recordatorios internos)
 * @param {string} title - Título de la notificación
 * @param {string} body - Cuerpo de la notificación
 * @param {number} seconds - Segundos hasta mostrar la notificación
 * @param {object} data - Datos adicionales
 */
export async function scheduleLocalNotification(title, body, seconds = 5, data = {}) {
    await Notifications.scheduleNotificationAsync({
        content: {
            title: title,
            body: body,
            data: data,
            sound: true,
        },
        trigger: {
            seconds: seconds,
        },
    });
}

export default {
    registerForPushNotificationsAsync,
    enviarTokenAlBackend,
    setupNotificationListeners,
    setBadgeCount,
    clearAllNotifications,
    cancelAllScheduledNotifications,
    scheduleLocalNotification
};
