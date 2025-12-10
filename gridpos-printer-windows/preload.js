const { contextBridge, ipcRenderer } = require('electron');

// Exponer APIs seguras al renderer
contextBridge.exposeInMainWorld('electronAPI', {
  getConfig: () => ipcRenderer.invoke('get-config'),
  saveConfig: (config) => ipcRenderer.invoke('save-config', config),
  getStatus: () => ipcRenderer.invoke('get-status'),
  testConnection: () => ipcRenderer.invoke('test-connection'),
  connectWebSocket: () => ipcRenderer.invoke('connect-websocket'),
  disconnectWebSocket: () => ipcRenderer.invoke('disconnect-websocket'),
  getPrinters: () => ipcRenderer.invoke('get-printers'),
  testPrinter: (printerName) => ipcRenderer.invoke('test-printer', printerName),
  
  // Eventos
  onWebSocketStatus: (callback) => {
    ipcRenderer.on('websocket-status', (event, data) => callback(data));
  },
  onWebSocketError: (callback) => {
    ipcRenderer.on('websocket-error', (event, data) => callback(data));
  },
  onLog: (callback) => {
    ipcRenderer.on('app-log', (event, data) => callback(data));
  },
  removeAllListeners: (channel) => {
    ipcRenderer.removeAllListeners(channel);
  }
});

