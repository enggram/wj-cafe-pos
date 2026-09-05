/**
 * Offline storage utility using IndexedDB for queuing order data
 * when the application loses network connectivity.
 */

const DB_NAME = 'wj-cafe-offline';
const DB_VERSION = 1;
const STORE_NAME = 'queued-orders';

/**
 * Opens (or creates) the IndexedDB database.
 * @returns {Promise<IDBDatabase>}
 */
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
            }
        };

        request.onsuccess = (event) => {
            resolve(event.target.result);
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };
    });
}

/**
 * Save order data locally for later sync.
 * @param {Object} orderData - The order data to queue
 * @returns {Promise<number>} The auto-generated ID of the stored record
 */
export async function saveOrderLocally(orderData) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const record = {
            ...orderData,
            queuedAt: new Date().toISOString(),
        };
        const request = store.add(record);

        request.onsuccess = (event) => {
            resolve(event.target.result);
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };

        transaction.oncomplete = () => {
            db.close();
        };
    });
}

/**
 * Retrieve all queued orders waiting to be synced.
 * @returns {Promise<Array>} Array of queued order objects
 */
export async function getQueuedOrders() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readonly');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.getAll();

        request.onsuccess = (event) => {
            resolve(event.target.result);
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };

        transaction.oncomplete = () => {
            db.close();
        };
    });
}

/**
 * Remove a queued order after successful sync.
 * @param {number} id - The ID of the order record to remove
 * @returns {Promise<void>}
 */
export async function removeQueuedOrder(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        const store = transaction.objectStore(STORE_NAME);
        const request = store.delete(id);

        request.onsuccess = () => {
            resolve();
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };

        transaction.oncomplete = () => {
            db.close();
        };
    });
}
