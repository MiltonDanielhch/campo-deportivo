import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import {
  ArrowLeft,
  ShoppingCart,
  ShieldCheck,
  QrCode,
  FileText,
  Lock,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCarritoStore } from '@/store/carritoStore';
import { api } from '@/lib/api';
import ResumenCarrito from '@/components/reserva/ResumenCarrito';
import FormularioSolicitante from '@/components/reserva/FormularioSolicitante';
import StepsReserva from '@/components/layout/StepsReserva';

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

      limpiar();
      navigate(`/pago/${codigoSeguimiento}`);
    } catch (err: any) {
      const status = err.response?.status;
      const dataErr = err.response?.data;

      if (status === 409) {
        setError(
          'Una de las franjas seleccionadas ya no está disponible. Por favor, volvé a elegir.',
        );
      } else if (status === 503) {
        setError(
          'El sistema de cobro no está disponible en este momento. Intentá nuevamente en unos minutos.',
        );
      } else if (status === 422 && dataErr?.errors) {
        setError(Object.values(dataErr.errors).flat().join(' '));
      } else {
        setError('Error al crear la reserva. Intentá nuevamente.');
      }

      setCargando(false);
    }
  };

  // ─── Carrito vacío ───
  if (franjas.length === 0) {
    return (
      <>
        <Helmet>
          <title>Carrito vacío - Canchas GAD Beni</title>
        </Helmet>
        <div className="min-h-screen bg-slate-50 flex items-center justify-center px-4">
          <div className="text-center max-w-sm">
            <div className="w-20 h-20 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-6">
              <ShoppingCart className="w-10 h-10 text-slate-400" />
            </div>
            <h1 className="text-2xl font-bold mb-2 tracking-tight">
              Tu carrito está vacío
            </h1>
            <p className="text-slate-500 mb-6">
              Seleccioná al menos una franja horaria para continuar con la reserva.
            </p>
            <Button asChild className="rounded-full">
              <Link to="/campos">Ver canchas disponibles</Link>
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

      <div className="min-h-screen bg-slate-50">
        <div className="container mx-auto px-4 py-6 md:py-10 max-w-6xl">
          {/* ─── Header ─── */}
          <div className="mb-6">
            <Button variant="ghost" asChild className="-ml-2 mb-4">
              <Link to="/campos" className="flex items-center gap-1.5">
                <ArrowLeft className="w-4 h-4" />
                Seguir eligiendo
              </Link>
            </Button>

            <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-2">
              Paso 2 de 3
            </p>
            <h1 className="text-2xl md:text-4xl font-bold tracking-tight mb-2">
              Confirmar reserva
            </h1>
            <p className="text-slate-600">
              Revisá las franjas elegidas y completá tus datos para emitir el comprobante.
            </p>
          </div>

          {/* ─── Stepper ─── */}
          <StepsReserva pasoActual={2} />

          {/* ─── Grid 2 columnas ─── */}
          <div className="grid lg:grid-cols-5 gap-6">
            {/* IZQ: Form + franjas */}
            <div className="lg:col-span-3 space-y-6">
              {error && (
                <div className="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-start gap-3">
                  <div className="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span className="text-red-600 text-sm font-bold">!</span>
                  </div>
                  <p className="text-sm">{error}</p>
                </div>
              )}

              {/* Franjas (visible en mobile, oculto en desktop porque ya está en el resumen) */}
              <div className="lg:hidden">
                <div className="flex items-center gap-2 mb-3">
                  <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                    <ShoppingCart className="w-4 h-4 text-teal-700" />
                  </div>
                  <h2 className="text-lg font-bold tracking-tight">
                    Franjas seleccionadas
                  </h2>
                </div>
                <ResumenCarrito />
              </div>

              {/* Form */}
              <FormularioSolicitante onSubmit={handleEnviar} cargando={cargando} />
            </div>

            {/* DER: Resumen sticky */}
            <div className="lg:col-span-2">
              <div className="lg:sticky lg:top-24">
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                  <div className="flex items-center gap-2 mb-4">
                    <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center">
                      <ShoppingCart className="w-4 h-4 text-teal-700" />
                    </div>
                    <h2 className="text-lg font-bold tracking-tight">
                      Resumen de tu reserva
                    </h2>
                  </div>

                  <ResumenCarrito compacto />

                  {/* Trust badges */}
                  <div className="mt-5 pt-5 border-t border-slate-100 space-y-2.5">
                    <div className="flex items-center gap-2.5 text-xs text-slate-600">
                      <QrCode className="w-4 h-4 text-teal-600 flex-shrink-0" />
                      <span>Pago seguro con QR bancario</span>
                    </div>
                    <div className="flex items-center gap-2.5 text-xs text-slate-600">
                      <FileText className="w-4 h-4 text-teal-600 flex-shrink-0" />
                      <span>Comprobante digital al instante</span>
                    </div>
                    <div className="flex items-center gap-2.5 text-xs text-slate-600">
                      <ShieldCheck className="w-4 h-4 text-teal-600 flex-shrink-0" />
                      <span>Datos protegidos y cifrados</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Trust bar (móvil) */}
          <div className="mt-8 flex items-center justify-center gap-2 text-xs text-slate-500 lg:hidden">
            <Lock className="w-3.5 h-3.5" />
            <span>Reserva segura · Gobierno Autónomo Departamental del Beni</span>
          </div>
        </div>
      </div>
    </>
  );
}
