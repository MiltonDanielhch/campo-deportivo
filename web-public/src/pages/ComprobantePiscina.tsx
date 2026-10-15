import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { ArrowLeft, Clock, Users, CheckCircle, XCircle, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { api } from '@/lib/api';
import type { PiscinaReserva } from '@/types/piscina';

export default function ComprobantePiscina() {
  const { codigo } = useParams<{ codigo: string }>();
  const [reserva, setReserva] = useState<PiscinaReserva | null>(null);
  const [cargando, setCargando] = useState(true);
  const [noEncontrado, setNoEncontrado] = useState(false);

  useEffect(() => {
    if (!codigo) return;
    api
      .get(`/public/piscina/reservas/${codigo}/estado`)
      .then((res) => {
        setReserva(res.data.data);
        setCargando(false);
      })
      .catch((err) => {
        if (err.response?.status === 404) {
          setNoEncontrado(true);
        }
        setCargando(false);
      });
  }, [codigo]);

  if (cargando) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <Loader2 className="w-10 h-10 animate-spin text-teal-600" />
      </div>
    );
  }

  if (noEncontrado || !reserva) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
        <Card className="max-w-md w-full">
          <CardContent className="py-16 text-center">
            <XCircle className="w-16 h-16 text-slate-400 mx-auto mb-4" />
            <h1 className="text-2xl font-bold mb-2">Reserva no encontrada</h1>
            <p className="text-slate-600 mb-6">
              No pudimos encontrar una reserva con ese código.
            </p>
            <Button asChild className="rounded-full">
              <Link to="/estado">Consultar otro código</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  const confirmada = reserva.estado === 'confirmada';
  const pendiente = reserva.estado === 'pendiente';

  return (
    <>
      <Helmet>
        <title>Comprobante - Piscina GAD Beni</title>
        <meta name="robots" content="noindex" />
      </Helmet>

      <div className="min-h-screen bg-slate-50 pb-16">
        <div className="container mx-auto px-4 py-6 md:py-10 max-w-3xl">
          {/* Header */}
          <div className="flex items-center gap-3 mb-6">
            <Button variant="ghost" asChild>
              <Link to="/" className="flex items-center gap-1.5">
                <ArrowLeft className="w-4 h-4" />
                Inicio
              </Link>
            </Button>
          </div>

          {/* Estado */}
          <Card className="mb-6">
            <CardContent className="py-8">
              <div className="flex items-center justify-center gap-4">
                {confirmada ? (
                  <CheckCircle className="w-16 h-16 text-emerald-600" />
                ) : pendiente ? (
                  <Clock className="w-16 h-16 text-amber-600" />
                ) : (
                  <XCircle className="w-16 h-16 text-red-600" />
                )}
                <div>
                  <h1 className="text-2xl font-bold mb-1">
                    {confirmada ? '¡Reserva Confirmada!' : pendiente ? 'Reserva Pendiente' : 'Reserva ' + reserva.estado}
                  </h1>
                  <p className="text-slate-600">
                    Código: {reserva.codigo_seguimiento}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Detalles */}
          <Card className="mb-6">
            <CardContent className="p-6 space-y-4">
              <div>
                <p className="text-sm text-slate-500 mb-1">Campo</p>
                <p className="font-semibold">{reserva.campo?.nombre}</p>
              </div>

              <div>
                <p className="text-sm text-slate-500 mb-1">Estado</p>
                <Badge
                  variant={confirmada ? 'default' : pendiente ? 'secondary' : 'destructive'}
                >
                  {reserva.estado}
                </Badge>
              </div>

              {reserva.monto_confirmado && (
                <div>
                  <p className="text-sm text-slate-500 mb-1">Monto pagado</p>
                  <p className="font-semibold text-lg">
                    Bs {Number(reserva.monto_confirmado).toFixed(2)}
                  </p>
                </div>
              )}

              {pendiente && reserva.expira_en && (
                <div>
                  <p className="text-sm text-slate-500 mb-1">Vence</p>
                  <p className="font-semibold text-amber-600">
                    {new Date(reserva.expira_en).toLocaleString('es-BO')}
                  </p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Detalles de la reserva */}
          <Card>
            <CardContent className="p-6">
              <h2 className="text-lg font-semibold mb-4">Detalles de la reserva</h2>
              <div className="space-y-3">
                {reserva.detalles?.map((detalle) => (
                  <div key={detalle.id} className="border rounded-lg p-4">
                    <div className="flex items-center justify-between mb-2">
                      <div className="flex items-center gap-2">
                        <Clock className="w-4 h-4 text-slate-400" />
                        <span className="font-medium">
                          {detalle.hora_inicio} - {detalle.hora_fin}
                        </span>
                      </div>
                      <span className="text-sm text-slate-500">
                        {detalle.fecha}
                      </span>
                    </div>
                    <div className="space-y-2">
                      {detalle.perfil === 'mayor_12' && <p className="text-sm">Mayores de 12</p>}
                      {detalle.perfil === 'menor_12' && <p className="text-sm">Menores de 12</p>}
                      {detalle.perfil === 'institucion' && <p className="text-sm">Institución</p>}
                      <div className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-2">
                          <Users className="w-4 h-4 text-slate-400" />
                          <span>{detalle.cantidad} personas</span>
                        </div>
                        <span className="font-semibold">
                          Bs {Number(detalle.precio_unitario).toFixed(2)}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Datos del pagador */}
          <Card className="mt-6">
            <CardContent className="p-6">
              <h2 className="text-lg font-semibold mb-4">Datos del pagador</h2>
              <div className="space-y-2 text-sm">
                <div>
                  <p className="text-slate-500">Nombre</p>
                  <p className="font-medium">{reserva.nombre_pagador}</p>
                </div>
                <div>
                  <p className="text-slate-500">Teléfono</p>
                  <p className="font-medium">{reserva.telefono_pagador}</p>
                </div>
                <div>
                  <p className="text-slate-500">CI / NIT</p>
                  <p className="font-medium">{reserva.ci_nit_pagador}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}
