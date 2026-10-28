import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import {
  ArrowLeft,
  MapPin,
  Clock,
  Users,
  Check,
  Plus,
  Minus,
  Info,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { api } from '@/lib/api';
import SelectorFechas from '@/components/campo/SelectorFechas';
import { piscinaService } from '@/services/piscinaService';
import type {
  PiscinaDisponibilidad,
  PiscinaTurno,
  PiscinaReservaItem,
} from '@/types/piscina';

interface Campo {
  id: string;
  nombre: string;
  direccion: string;
  estado: string;
  imagen_url: string | null;
}

const ETIQUETA_PERFIL: Record<string, string> = {
  mayor_12: 'Mayores de 12',
  menor_12: 'Menores de 12',
  institucion: 'Institución',
};

interface PiscinaDetalleProps {
  campoId: string;
}

export default function PiscinaDetalle({ campoId }: PiscinaDetalleProps) {
  const navigate = useNavigate();
  const [campo, setCampo] = useState<Campo | null>(null);
  const [disponibilidad, setDisponibilidad] = useState<PiscinaDisponibilidad | null>(null);
  const [fecha, setFecha] = useState<string>(() => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  });
  const [cargandoCampo, setCargandoCampo] = useState(true);
  const [cargandoDisponibilidad, setCargandoDisponibilidad] = useState(true);
  const [turnoSeleccionado, setTurnoSeleccionado] = useState<PiscinaTurno | null>(null);
  const [itemsSeleccionados, setItemsSeleccionados] = useState<Record<string, number>>({});
  const [creandoReserva, setCreandoReserva] = useState(false);
  const [mostrarFormulario, setMostrarFormulario] = useState(false);
  const [datosPagador, setDatosPagador] = useState({
    nombre: '',
    telefono: '',
    ciNit: '',
  });

  useEffect(() => {
    if (!campoId) return;
    api
      .get(`/public/campos/${campoId}`)
      .then((res) => setCampo(res.data.data))
      .catch((err) => console.error('Error al cargar campo:', err))
      .finally(() => setCargandoCampo(false));
  }, [campoId]);

  useEffect(() => {
    if (!campoId || !campo) return;
    setCargandoDisponibilidad(true);
    piscinaService
      .obtenerTurnos(campoId, fecha)
      .then((data) => setDisponibilidad(data))
      .catch((err) => console.error('Error al cargar disponibilidad:', err))
      .finally(() => setCargandoDisponibilidad(false));
  }, [campoId, campo, fecha]);

  const handleSeleccionarTurno = (turno: PiscinaTurno) => {
    setTurnoSeleccionado(turno);
    setItemsSeleccionados({});
  };

  const handleCambiarCantidad = (perfil: string, delta: number) => {
    setItemsSeleccionados((prev) => {
      const nuevo = { ...prev };
      const actual = nuevo[perfil] || 0;
      const cantidad = Math.max(0, actual + delta);
      if (cantidad === 0) {
        delete nuevo[perfil];
      } else {
        nuevo[perfil] = cantidad;
      }
      return nuevo;
    });
  };

  const montoTotal = Object.entries(itemsSeleccionados).reduce((total, [perfil, cantidad]) => {
    const perfilData = turnoSeleccionado?.perfiles.find((p) => p.perfil === perfil);
    return total + (perfilData?.precio || 0) * cantidad;
  }, 0);

  const totalPersonas = Object.values(itemsSeleccionados).reduce((sum, cantidad) => sum + cantidad, 0);

  const handleCrearReserva = async () => {
    if (!turnoSeleccionado || totalPersonas === 0) return;

    // Mostrar formulario de datos del pagador
    setMostrarFormulario(true);
  };

  const handleConfirmarReserva = async () => {
    if (!turnoSeleccionado || totalPersonas === 0) return;

    // Validar datos del pagador
    if (!datosPagador.nombre || !datosPagador.telefono || !datosPagador.ciNit) {
      alert('Por favor completa todos los datos del pagador');
      return;
    }

    setCreandoReserva(true);
    try {
      const items: PiscinaReservaItem[] = Object.entries(itemsSeleccionados).map(([perfil, cantidad]) => ({
        perfil: perfil as any,
        cantidad,
      }));

      const resultado = await piscinaService.crearReserva({
        fecha,
        turno_id: turnoSeleccionado.id,
        items,
        nombre_pagador: datosPagador.nombre,
        telefono_pagador: datosPagador.telefono,
        ci_nit_pagador: datosPagador.ciNit,
      });

      // Redirigir al flujo de pago unificado (mismo patrón que campos)
      navigate(`/pago/${resultado.codigo_seguimiento}`);
      setMostrarFormulario(false);
      setItemsSeleccionados({});
      setTurnoSeleccionado(null);
    } catch (error) {
      console.error('Error al crear reserva:', error);
      alert('Error al crear reserva');
    } finally {
      setCreandoReserva(false);
    }
  };

  if (cargandoCampo) {
    return (
      <div className="container mx-auto px-4 py-6 max-w-6xl">
        <div className="animate-pulse space-y-4">
          <div className="h-10 bg-slate-200 rounded-full w-32" />
          <div className="aspect-[16/7] bg-slate-200 rounded-2xl" />
          <div className="h-16 bg-slate-200 rounded-xl" />
        </div>
      </div>
    );
  }

  if (!campo) {
    return (
      <div className="container mx-auto px-4 py-16 text-center max-w-md">
        <div className="w-20 h-20 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-6">
          <Info className="w-10 h-10 text-slate-400" />
        </div>
        <h1 className="text-2xl font-bold mb-3 tracking-tight">
          Campo no encontrado
        </h1>
        <Button asChild className="rounded-full">
          <Link to="/campos">Volver al listado</Link>
        </Button>
      </div>
    );
  }

  return (
    <>
      <Helmet>
        <title>{campo.nombre} - Piscina GAD Beni</title>
        <meta
          name="description"
          content={`Reserva ${campo.nombre} en ${campo.direccion}. Turnos por perfil.`}
        />
      </Helmet>

      <div className="min-h-screen bg-slate-50 pb-28">
        {/* Header */}
        <div className="bg-white border-b">
          <div className="container mx-auto px-4 py-4 max-w-6xl">
            <Button asChild variant="ghost" size="sm" className="mb-4">
              <Link to="/campos">
                <ArrowLeft className="w-4 h-4 mr-2" />
                Volver
              </Link>
            </Button>
            <h1 className="text-3xl font-bold tracking-tight mb-2">{campo.nombre}</h1>
            <div className="flex items-center gap-2 text-slate-600">
              <MapPin className="w-4 h-4" />
              <span>{campo.direccion}</span>
            </div>
          </div>
        </div>

        <div className="container mx-auto px-4 py-6 max-w-6xl">
          {/* Selector de fecha */}
          <SelectorFechas
            fechaSeleccionada={fecha}
            onFechaChange={setFecha}
          />

          {/* Turnos disponibles */}
          {cargandoDisponibilidad ? (
            <div className="mt-6 space-y-3">
              {[1, 2, 3].map((i) => (
                <Card key={i} className="animate-pulse">
                  <CardContent className="p-4">
                    <div className="h-6 bg-slate-200 rounded w-1/3 mb-2" />
                    <div className="h-4 bg-slate-200 rounded w-2/3" />
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : !disponibilidad?.abierto ? (
            <Card className="mt-6">
              <CardContent className="p-8 text-center">
                <Info className="w-12 h-12 mx-auto text-slate-400 mb-4" />
                <h3 className="text-lg font-semibold mb-2">No hay turnos disponibles</h3>
                <p className="text-slate-500">
                  No hay turnos configurados para esta fecha.
                </p>
              </CardContent>
            </Card>
          ) : disponibilidad.turnos.length === 0 ? (
            <Card className="mt-6">
              <CardContent className="p-8 text-center">
                <Info className="w-12 h-12 mx-auto text-slate-400 mb-4" />
                <h3 className="text-lg font-semibold mb-2">No hay turnos disponibles</h3>
                <p className="text-slate-500">
                  {(disponibilidad.turnos_ya_comenzados ?? 0) > 0
                    ? 'Los turnos de hoy ya comenzaron. Probá mañana o elegí otra fecha.'
                    : 'No hay turnos disponibles para esta fecha.'}
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="mt-6 space-y-3">
              {disponibilidad.turnos.map((turno) => (
                <Card
                  key={turno.id}
                  className={`cursor-pointer transition-all ${
                    turnoSeleccionado?.id === turno.id
                      ? 'border-teal-500 ring-2 ring-teal-500/20'
                      : 'hover:border-teal-300'
                  }`}
                  onClick={() => handleSeleccionarTurno(turno)}
                >
                  <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <Clock className="w-5 h-5 text-teal-600" />
                        <div>
                          <p className="font-semibold">
                            {turno.hora_inicio} - {turno.hora_fin}
                          </p>
                          <p className="text-sm text-slate-500">
                            {turno.perfiles.length} perfiles disponibles
                          </p>
                        </div>
                      </div>
                      {turnoSeleccionado?.id === turno.id && (
                        <Check className="w-5 h-5 text-teal-600" />
                      )}
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          {/* Selector de perfiles y cantidades */}
          {turnoSeleccionado && (
            <Card className="mt-6">
              <CardContent className="p-6">
                <h3 className="text-lg font-semibold mb-4">
                  Selecciona cantidad por perfil
                </h3>
                <div className="space-y-4">
                  {turnoSeleccionado.perfiles.map((perfil) => (
                    <div
                      key={perfil.perfil}
                      className="flex items-center justify-between p-4 border rounded-lg"
                    >
                      <div>
                        <p className="font-medium">{ETIQUETA_PERFIL[perfil.perfil]}</p>
                        <p className="text-sm text-slate-500">
                          Bs {perfil.precio.toFixed(2)} · Disponible: {perfil.disponible}
                        </p>
                      </div>
                      <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                          <Button
                            variant="outline"
                            size="icon"
                            onClick={() => handleCambiarCantidad(perfil.perfil, -1)}
                            disabled={!itemsSeleccionados[perfil.perfil]}
                          >
                            <Minus className="w-4 h-4" />
                          </Button>
                          <span className="w-8 text-center font-medium">
                            {itemsSeleccionados[perfil.perfil] || 0}
                          </span>
                          <Button
                            variant="outline"
                            size="icon"
                            onClick={() => handleCambiarCantidad(perfil.perfil, 1)}
                            disabled={
                              (itemsSeleccionados[perfil.perfil] || 0) >= perfil.disponible
                            }
                          >
                            <Plus className="w-4 h-4" />
                          </Button>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>

                {/* Resumen y botón de reserva */}
                {totalPersonas > 0 && !mostrarFormulario && (
                  <div className="mt-6 pt-6 border-t">
                    <div className="flex items-center justify-between mb-4">
                      <div className="flex items-center gap-2 text-slate-600">
                        <Users className="w-4 h-4" />
                        <span>{totalPersonas} personas</span>
                      </div>
                      <div className="text-lg font-bold">
                        Total: Bs {montoTotal.toFixed(2)}
                      </div>
                    </div>
                    <Button
                      className="w-full"
                      onClick={handleCrearReserva}
                      disabled={creandoReserva}
                    >
                      {creandoReserva ? 'Creando reserva...' : 'Continuar al pago'}
                    </Button>
                  </div>
                )}

                {/* Formulario de datos del pagador */}
                {mostrarFormulario && (
                  <div className="mt-6 pt-6 border-t space-y-4">
                    <h3 className="text-lg font-semibold">Datos del Pagador</h3>
                    <div className="space-y-3">
                      <div>
                        <label className="block text-sm font-medium mb-1">Nombre completo</label>
                        <input
                          type="text"
                          className="w-full px-3 py-2 border rounded-lg"
                          placeholder="Tu nombre completo"
                          value={datosPagador.nombre}
                          onChange={(e) => setDatosPagador({ ...datosPagador, nombre: e.target.value })}
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-1">Teléfono</label>
                        <input
                          type="tel"
                          className="w-full px-3 py-2 border rounded-lg"
                          placeholder="Tu número de teléfono"
                          value={datosPagador.telefono}
                          onChange={(e) => setDatosPagador({ ...datosPagador, telefono: e.target.value })}
                          required
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium mb-1">CI / NIT</label>
                        <input
                          type="text"
                          className="w-full px-3 py-2 border rounded-lg"
                          placeholder="Número de documento"
                          value={datosPagador.ciNit}
                          onChange={(e) => setDatosPagador({ ...datosPagador, ciNit: e.target.value })}
                          required
                        />
                      </div>
                    </div>
                    <div className="flex gap-3 pt-4">
                      <Button
                        variant="outline"
                        className="flex-1"
                        onClick={() => setMostrarFormulario(false)}
                      >
                        Volver
                      </Button>
                      <Button
                        className="flex-1"
                        onClick={handleConfirmarReserva}
                        disabled={creandoReserva}
                      >
                        {creandoReserva ? 'Confirmando...' : 'Confirmar Reserva'}
                      </Button>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}
