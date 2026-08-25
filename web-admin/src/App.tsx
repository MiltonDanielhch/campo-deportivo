import { useEffect, useState } from 'react';
import apiClient from './services/apiClient';
import type { HealthCheckResponse } from './types';
import './App.css';

function App() {
  const [health, setHealth] = useState<HealthCheckResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const checkHealth = async () => {
      try {
        const response = await apiClient.get<HealthCheckResponse>('/health');
        setHealth(response.data);
        setError(null);
      } catch (err) {
        setError('No se pudo conectar con el backend de Canchas');
        setHealth(null);
      } finally {
        setLoading(false);
      }
    };

    checkHealth();
  }, []);

  if (loading) {
    return (
      <div style={{ padding: '50px', textAlign: 'center' }}>
        <h1>Verificando conexión con backend...</h1>
      </div>
    );
  }

  return (
    <div style={{ padding: '50px', maxWidth: '600px', margin: '0 auto' }}>
      <h1>Panel Administrativo - Canchas Deportivas</h1>

      {health ? (
        <div style={{ marginTop: '30px', padding: '20px', background: '#d4edda', border: '1px solid #c3e6cb', borderRadius: '5px' }}>
          <h2>✅ Backend conectado</h2>
          <table style={{ width: '100%', marginTop: '15px' }}>
            <tbody>
              <tr><td><strong>Status:</strong></td><td>{health.status}</td></tr>
              <tr><td><strong>Service:</strong></td><td>{health.service}</td></tr>
              <tr><td><strong>Version:</strong></td><td>{health.version}</td></tr>
              <tr><td><strong>Timezone:</strong></td><td>{health.timezone}</td></tr>
              <tr><td><strong>Timestamp:</strong></td><td>{health.timestamp}</td></tr>
            </tbody>
          </table>
        </div>
      ) : (
        <div style={{ marginTop: '30px', padding: '20px', background: '#f8d7da', border: '1px solid #f5c6cb', borderRadius: '5px' }}>
          <h2>❌ {error}</h2>
          <p>Verifica que el backend esté corriendo en <code>http://localhost:8000</code></p>
        </div>
      )}

      <div style={{ marginTop: '30px', padding: '20px', background: '#f8f9fa', border: '1px solid #dee2e6', borderRadius: '5px' }}>
        <h3>Estado del proyecto</h3>
        <ul>
          <li>✅ Backend Laravel 13 operativo</li>
          <li>✅ Panel web React + Vite + TypeScript conectado</li>
          <li>⏳ Login y autenticación (próximo módulo)</li>
          <li>⏳ CRUD de campos deportivos (próximo módulo)</li>
        </ul>
      </div>
    </div>
  );
}

export default App;
