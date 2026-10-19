import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Check, Copy, Download, Loader2, MapPin, MessageCircle, Plus, QrCode } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
} from '@/components/ui/select';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type { CampoOption } from '@/types/reservas';
import { QRCodeCanvas } from 'qrcode.react';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreada: () => void;
}

interface Bloque {
  hora_inicio: string;
  hora_fin: string;
  estado: string;
}

interface Resultado {
  codigo_seguimiento: string;
  monto_total: number;
  referencia_recaudaciones: string | null;
  qr_string: string | null;
}

const hhmm = (h: string) => h.substring(0, 5);

export default function DialogCrearReservaVentanilla({ open, onOpenChange, onCreada }: Props) {
  const [campos, setCampos] = useState<CampoOption[]>([]);
  const [campoId, setCampoId] = useState<string>('');
  const [campoNombre, setCampoNombre] = useState<string>('');
  const [fecha, setFecha] = useState<string>('');
  const [bloques, setBloques] = useState<Bloque[]>([]);
  const [cargandoSlots, setCargandoSlots] = useState(false);
  const [slots, setSlots] = useState<string[]>([]);

  const [nombre, setNombre] = useState('');
  const [ciNit, setCiNit] = useState('');
  const [telefono, setTelefono] = useState('');

  const [enviando, setEnviando] = useState(false);
  const [resultado, setResultado] = useState<Resultado | null>(null);
  const [copiado, setCopiado] = useState<string | null>(null);

  const cerrar = () => {
    onOpenChange(false);
    setTimeout(() => {
      setCampoId(''); setCampoNombre(''); setFecha(''); setBloques([]); setSlots([]);
      setNombre(''); setCiNit(''); setTelefono(''); setResultado(null);
    }, 200);
  };

  useEffect(() => {
    if (!open) return;
    solicitudesReservaService
      .listarCamposPublicos()
      .then((lista) => {
        setCampos(lista);
        // Diagnóstico: si el endpoint no trae nombre legible, lo vemos en consola.
        const sinNombre = lista.filter((c) => !c.nombre || c.nombre === c.id);
        if (sinNombre.length > 0) {
          console.warn('[ventanilla] campos sin nombre legible:', sinNombre);
        }
      })
      .catch(() => setCampos([]));
  }, [open]);

  const handleCampoChange = (nuevoId: string) => {
    setCampoId(nuevoId);
    const campo = campos.find((c) => c.id === nuevoId);
    setCampoNombre(campo?.nombre && campo.nombre !== campo.id ? campo.nombre : '');
  };

  const cargarSlots = useCallback(async () => {
    if (!campoId || !fecha) return;
    setCargandoSlots(true);
    setSlots([]);
    try {
      const disp = await solicitudesReservaService.disponibilidad(campoId, fecha);
      setBloques(disp.abierto ? disp.bloques.filter((b) => b.estado === 'libre') : []);
    } catch {
      setBloques([]);
      toast.error('No se pudo consultar la disponibilidad del campo.');
    } finally {
      setCargandoSlots(false);
    }
  }, [campoId, fecha]);

  useEffect(() => {
    cargarSlots();
  }, [cargarSlots]);

  const toggleSlot = (hora: string) =>
    setSlots((prev) => (prev.includes(hora) ? prev.filter((h) => h !== hora) : [...prev, hora].sort()));

  const copiar = async (texto: string, clave: string) => {
    await navigator.clipboard.writeText(texto);
    setCopiado(clave);
    setTimeout(() => setCopiado(null), 1500);
  };

  const descargarQr = () => {
    const canvas = document.getElementById('qr-ventanilla') as HTMLCanvasElement | null;
    if (!canvas) return;
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `qr-${resultado?.codigo_seguimiento ?? 'reserva'}.png`;
    a.click();
  };

  const enviarWhatsapp = () => {
    if (!resultado) return;
    const texto = encodeURIComponent(
      `Reserva ${resultado.codigo_seguimiento}\nMonto: Bs ${resultado.monto_total.toFixed(2)}\n` +
      `Código de pago: ${resultado.referencia_recaudaciones ?? ''}\n` +
      `Pagá en ventanilla del Banco Unión o escaneá el QR.`,
    );
    window.open(`https://wa.me/?text=${texto}`, '_blank');
  };

  const enviar = async () => {
    if (!campoId || !fecha || slots.length === 0) {
      toast.error('Elegí al menos una franja libre.');
      return;
    }
    if (!nombre.trim() || !ciNit.trim()) {
      toast.error('Nombre y CI/NIT del pagador son obligatorios.');
      return;
    }

    const bloqueDe = (hora: string) => bloques.find((b) => b.hora_inicio === hora)!;

    setEnviando(true);
    try {
      const res = await solicitudesReservaService.crearDesdeVentanilla({
        franjas: slots.map((hora) => ({
          campo_id: campoId,
          fecha,
          hora_inicio: bloqueDe(hora).hora_inicio,
          hora_fin: bloqueDe(hora).hora_fin,
        })),
        nombre_pagador: nombre.trim(),
        ci_nit_pagador: ciNit.trim(),
        telefono_pagador: telefono.trim() || undefined,
      });
      setResultado(res.data);
      onCreada();
    } catch (error: any) {
      const errs = error.response?.data?.errors?.franjas;
      toast.error(Array.isArray(errs) ? errs[0] : error.response?.data?.message ?? 'No se pudo crear la reserva.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(v) => (v ? onOpenChange(v) : cerrar())}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Plus className="size-5 text-teal-600" />
            Nueva reserva de ventanilla
          </DialogTitle>
          <DialogDescription>
            Generá la reserva y el código de pago SIREB para el ciudadano. El precio sale del espejo de SIREB, no se fija acá.
          </DialogDescription>
        </DialogHeader>

        {resultado ? (
          <div className="space-y-4 py-2">
            <div className="rounded-xl border border-emerald-300/60 bg-emerald-500/10 p-4 text-center dark:border-emerald-700">
              <p className="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
                Reserva creada
              </p>
              <p className="mt-1 font-mono text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                {resultado.codigo_seguimiento}
              </p>
              <p className="mt-1 text-lg font-bold tabular-nums text-emerald-900 dark:text-emerald-100">
                Bs {resultado.monto_total.toFixed(2)}
              </p>
            </div>

            {resultado.referencia_recaudaciones ? (
              <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
                <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Código de pago en ventanilla
                </p>

                {/* QR escaneable (igual que el público) */}
                {resultado.qr_string && (
                  <div className="flex flex-col items-center gap-3 py-2">
                    <div className="rounded-xl border bg-white p-3">
                      <QRCodeCanvas
                        id="qr-ventanilla"
                        value={resultado.qr_string}
                        size={220}
                        level="M"
                        marginSize={2}
                      />
                    </div>
                    <div className="flex flex-wrap justify-center gap-2">
                      <Button variant="outline" size="sm" onClick={descargarQr}>
                        <Download className="mr-2 size-4" />
                        Descargar QR
                      </Button>
                      <Button variant="outline" size="sm" onClick={enviarWhatsapp}>
                        <MessageCircle className="mr-2 size-4" />
                        Enviar por WhatsApp
                      </Button>
                    </div>
                  </div>
                )}

                <div className="flex items-center justify-between gap-2">
                  <code className="font-mono text-xl font-bold tracking-widest">
                    {resultado.referencia_recaudaciones}
                  </code>
                  <Button variant="ghost" size="icon" onClick={() => copiar(resultado.referencia_recaudaciones!, 'ref')}>
                    {copiado === 'ref' ? <Check className="size-4 text-green-600" /> : <Copy className="size-4" />}
                  </Button>
                </div>
                {resultado.qr_string && (
                  <div className="flex items-center justify-between gap-2 pt-1">
                    <code className="truncate font-mono text-xs text-muted-foreground">{resultado.qr_string}</code>
                    <Button variant="ghost" size="icon" onClick={() => copiar(resultado.qr_string!, 'qr')}>
                      {copiado === 'qr' ? <Check className="size-4 text-green-600" /> : <Copy className="size-4" />}
                    </Button>
                  </div>
                )}
                <p className="flex items-center gap-1.5 pt-1 text-xs text-muted-foreground">
                  <QrCode className="size-3.5" />
                  Presentá este código o escaneá el QR en ventanilla del Banco Unión con tu CI.
                </p>
              </div>
            ) : (
              <p className="rounded-xl border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
                La reserva quedó pendiente: SIREB no respondió y el reintento corre en segundo plano. El código de pago aparece en el detalle en unos minutos.
              </p>
            )}

            <Button className="w-full" onClick={cerrar}>
              Listo
            </Button>
          </div>
        ) : (
          <div className="space-y-5 py-2">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="min-w-0 space-y-2">
                <Label>Campo *</Label>
                <Select value={campoId} onValueChange={handleCampoChange}>
                  <SelectTrigger className="w-full" aria-label="Campo deportivo">
                    {/* Render manual: no dependemos de que Radix resuelva el texto */}
                    <span className="flex min-w-0 items-center gap-2 text-left">
                      <MapPin className="size-4 shrink-0 text-muted-foreground" />
                      <span className={`truncate ${campoNombre ? '' : 'text-muted-foreground'}`}>
                        {campoNombre || 'Elegí el campo'}
                      </span>
                    </span>
                  </SelectTrigger>
                  <SelectContent>
                    {campos.length === 0 ? (
                      <div className="px-2 py-1.5 text-sm text-muted-foreground">
                        Sin campos activos disponibles
                      </div>
                    ) : (
                      campos.map((c) => (
                        <SelectItem key={c.id} value={c.id}>
                          {c.nombre || c.id}
                        </SelectItem>
                      ))
                    )}
                  </SelectContent>
                </Select>
              </div>
              <div className="min-w-0 space-y-2">
                <Label>Fecha *</Label>
                <Input
                  type="date"
                  value={fecha}
                  min={new Date().toISOString().substring(0, 10)}
                  onChange={(e) => setFecha(e.target.value)}
                />
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label>Franjas libres *</Label>
                {cargandoSlots && <Loader2 className="size-4 animate-spin text-muted-foreground" />}
              </div>
              {!campoId || !fecha ? (
                <p className="text-sm text-muted-foreground">Elegí campo y fecha para ver horarios.</p>
              ) : bloques.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  {cargandoSlots ? 'Consultando…' : 'Sin franjas libres ese día.'}
                </p>
              ) : (
                <div className="flex flex-wrap gap-2">
                  {bloques.map((b) => {
                    const activo = slots.includes(b.hora_inicio);
                    return (
                      <Button
                        key={b.hora_inicio}
                        type="button"
                        variant={activo ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => toggleSlot(b.hora_inicio)}
                      >
                        {hhmm(b.hora_inicio)}–{hhmm(b.hora_fin)}
                      </Button>
                    );
                  })}
                </div>
              )}
              {slots.length > 0 && (
                <Badge variant="secondary">
                  {slots.length} {slots.length === 1 ? 'franja' : 'franjas'} · el monto lo confirma SIREB
                </Badge>
              )}
            </div>

            <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
              <p className="text-sm font-semibold">Datos del pagador</p>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label>Nombre completo *</Label>
                  <Input value={nombre} onChange={(e) => setNombre(e.target.value)} />
                </div>
                <div className="space-y-1.5">
                  <Label>CI / NIT *</Label>
                  <Input value={ciNit} onChange={(e) => setCiNit(e.target.value)} />
                </div>
                <div className="space-y-1.5 sm:col-span-2">
                  <Label>Teléfono (opcional)</Label>
                  <Input value={telefono} onChange={(e) => setTelefono(e.target.value)} />
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={cerrar} disabled={enviando}>
                Cancelar
              </Button>
              <Button onClick={enviar} disabled={enviando}>
                {enviando && <Loader2 className="mr-2 size-4 animate-spin" />}
                Crear reserva y código de pago
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}