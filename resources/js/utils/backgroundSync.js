/**
 * Background sync utility that watches for connectivity restoration
 * and sends queued orders to the server within 5 seconds.
 */

import { getQueuedOrders, removeQueuedOrder } from './offlineStorage.js';
import axios from 'axios';

let isSyncing = false;
let syncTimeout = null;

/**
 * Attempt to sync all queued orders to the server.
 * Removes each order from the local queue after a successful send.
 */
async function syncQueuedOrders() {
    if (isSyncing) return;
    isSyncing = true;

    try {
        const orders = await getQueuedOrders();

        for (const order of orders) {
            try {
                const { id, queuedAt, ...orderData } = order;
                await axios.post('/orders', orderData);
                await removeQueuedOrder(id);
            } catch (error) {
                // If a single order fails, continue trying the rest
                console.warn('[BackgroundSync] Failed to sync order:', order.id, error);
            }
        }
    } catch (error) {
        console.error('[BackgroundSync] Failed to retrieve queued orders:', error);
    } finally {
        isSyncing = false;
    }
}

/**
 * Handle the online event — trigger sync within 5 seconds.
 */
function handleOnline() {
    if (syncTimeout) {
        clearTimeout(syncTimeout);
    }

    // Sync queued orders within 5 seconds of connectivity restoration
    syncTimeout = setTimeout(() => {
        syncQueuedOrders();
    }, 1000); // Start sync after 1 second to allow connection to stabilize
}

/**
 * Initialize background sync listeners.
 * Call this once at app startup (e.g., in app.js or AppLayout).
 */
export function initBackgroundSync() {
    window.addEventListener('online', handleOnline);

    // Also try to sync on startup if online and there might be queued orders
    if (navigator.onLine) {
        syncQueuedOrders();
    }
}

/**
 * Tear down background sync listeners.
 * Useful for cleanup in tests or when unmounting.
 */
export function destroyBackgroundSync() {
    window.removeEventListener('online', handleOnline);
    if (syncTimeout) {
        clearTimeout(syncTimeout);
        syncTimeout = null;
    }
}
