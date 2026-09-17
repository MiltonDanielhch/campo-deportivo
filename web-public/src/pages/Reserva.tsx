import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { ArrowLeft, ShoppingCart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCarritoStore } from '@/store/carritoStore';
import { api } from '@/lib/api';
import ResumenCarrito from '@/components/reserva/ResumenCarrito';
import FormularioSolicitante from '@/components/reserva/FormularioSolicitante';

export default function Reserva() {
  const navigate = useNavigate();
  const { franjas, limpiar } = useCarritoStore();
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleEnviar = async (data: {
    nombre_pagador: string;
    telefono_pagador: string;
    ci_nit_pagador?: string;
  }) => {
    setCargando(true);
    setError(null);

    try {
      const payload = {
        nombre_pagador: data.nombre_pagador,
        telefono_pagador: data.telefono_pagador,
        ci_nit_pagador: data.ci_nit_pagador || null,
        franjas: franjas.map((f) => ({
          campo_id: f.campoId,
          fecha: f.fecha,
          hora_inicio: f.horaInicio,
          hora_fin: f.horaFin,
        })),
      };

      const res = await api.post('/public/solicitudes-reserva', payload);
      const codigoSeguimiento = res.data.data.codigo_seguimiento;

      // Limpiar carrito y navegar a la página de pago
      limpiar();
      navigate(`/pago/${codigoSeguimiento}`);
    } catch (err: any) {
      const status = err.response?.status;
      const data = err.response?.data;

      if (status === 409) {
        setError(
          'Una de las franjas seleccionadas ya no está disponible. Por favor, vuelve a elegir.',
        );
      } else if (status === 503) {
        setError(
          'El sistema de cobro no está disponible en este momento. Intenta nuevamente en unos minutos.',
        );
      } else if (status === 422 && data?.errors) {
        setError(Object.values(data.errors).flat().join(' '));
      } else {
        setError('Error al crear la reserva. Intenta nuevamente.');
      }

      setCargando(false);
    }
  };

  if (franjas.length === 0) {
    return (
      <>
        <Helmet>
          <title>Carrito vacío - Canchas GAD Beni</title>
        </Helmet>
        <div className="min-h-screen bg-gray-50 flex items-center justify-center">
          <div className="text-center">
            <ShoppingCart className="w-16 h-16 text-gray-400 mx-auto mb-4" />
            <h1 className="text-2xl font-bold mb-2">Tu carrito está vacío</h1>
            <p className="text-gray-600 mb-6">
              Selecciona al menos una franja horaria para continuar.
            </p>
            <Button asChild>
              <Link to="/campos">Ver canchas</Link>
            </Button>
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      <Helmet>
        <title>Confirmar Reserva - Canchas GAD Beni</title>
      </Helmet>

      <div className="min-h-screen bg-gray-50">
        <div className="container mx-auto px-4 py-8 max-w-3xl">
          <Button variant="ghost" asChild className="mb-4">
            <Link to="/campos">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Seguir eligiendo
            </Link>
          </Button>

          <h1 className="text-3xl font-bold mb-6">Confirmar reserva</h1>

          {error && (
            <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
              {error}
            </div>
          )}

          <ResumenCarrito />
          <FormularioSolicitante onSubmit={handleEnviar} cargando={cargando} />
        </div>
      </div>
    </>
  );
}