import axios from 'axios';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

// Manejo centralizado de los errores del dominio de reserva
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 409) {
      console.warn('Franja no disponible:', error.response.data);
    }
    if (error.response?.status === 503) {
      console.warn('Servicio de cobro no disponible:', error.response.data);
    }
    return Promise.reject(error);
  },
);
