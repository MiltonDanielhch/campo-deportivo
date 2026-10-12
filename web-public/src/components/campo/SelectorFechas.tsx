import { useState } from 'react';
import { Calendar as CalendarIcon } from 'lucide-react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

interface SelectorFechasProps {
  fechaSeleccionada: string;
  onFechaChange: (fecha: string) => void;
}

const nombresDias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

/**
 * Genera fecha ISO (yyyy-MM-dd) usando componentes LOCALES.
 * Reemplaza `new Date().toISOString().split('T')[0]` que convierte a UTC
 * y rompe el día en zonas horarias negativas (Bolivia UTC-4).
 */
const fechaLocalISO = (date: Date): string => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

export default function SelectorFechas({
  fechaSeleccionada,
  onFechaChange,
}: SelectorFechasProps) {
  const [open, setOpen] = useState(false);

  const dias: { fecha: string; nombreDia: string; diaMes: string }[] = [];
  for (let i = 0; i < 14; i++) {
    const d = new Date();
    d.setDate(d.getDate() + i);
    const fechaISO = fechaLocalISO(d); // ← FIX
    dias.push({
      fecha: fechaISO,
      nombreDia: i === 0 ? 'Hoy' : i === 1 ? 'Mañana' : nombresDias[d.getDay()],
      diaMes: `${d.getDate()} ${nombresMeses[d.getMonth()]}`,
    });
  }

  const estaEnRango = dias.some((d) => d.fecha === fechaSeleccionada);

  let chipFuera: { nombreDia: string; diaMes: string } | null = null;
  if (!estaEnRango && fechaSeleccionada) {
    // Parseo seguro usando componentes locales (no UTC)
    const [y, m, d] = fechaSeleccionada.split('-').map(Number);
    const parsed = new Date(y, m - 1, d);
    if (!isNaN(parsed.getTime())) {
      chipFuera = {
        nombreDia: nombresDias[parsed.getDay()],
        diaMes: `${parsed.getDate()} ${nombresMeses[parsed.getMonth()]}`,
      };
    }
  }

  const [fy, fm, fd] = fechaSeleccionada.split('-').map(Number);
  const fechaDate = fechaSeleccionada && !isNaN(fy)
    ? new Date(fy, fm - 1, fd)
    : undefined;

  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);

  const handleCalendarSelect = (date: Date | undefined) => {
    if (date) {
      onFechaChange(format(date, 'yyyy-MM-dd'));
      setOpen(false);
    }
  };

  return (
    <div className="mb-6 flex items-stretch gap-2">
      {/* ─── Fila de 14 días: scroll horizontal en pantallas chicas ─── */}
      <div className="-ml-4 min-w-0 flex-1 overflow-x-auto pb-2 pl-4 sm:ml-0 sm:pl-0 scrollbar-none">
        <div className="flex w-max items-stretch gap-2 pr-2 snap-x snap-mandatory">
          {dias.map((dia) => {
            const activo = dia.fecha === fechaSeleccionada;
            return (
              <Button
                key={dia.fecha}
                variant={activo ? 'default' : 'outline'}
                onClick={() => onFechaChange(dia.fecha)}
                className={`snap-start flex flex-col items-center min-w-[70px] h-auto py-2 ${
                  activo ? 'bg-teal-600 hover:bg-teal-700' : ''
                }`}
              >
                <span className="text-xs font-normal">{dia.nombreDia}</span>
                <span className="text-sm font-semibold">{dia.diaMes}</span>
              </Button>
            );
          })}
        </div>
      </div>

      {/* ─── Botón calendario: FIJO, siempre visible ─── */}
      <div className="flex-shrink-0 pb-2">
        <Popover open={open} onOpenChange={setOpen}>
          <PopoverTrigger asChild>
            {chipFuera ? (
              <Button
                variant="default"
                title="Fecha fuera de los próximos 14 días. Click para elegir otra."
                className="relative flex flex-col items-center min-w-[70px] h-auto py-2 bg-teal-600 hover:bg-teal-700"
              >
                <CalendarIcon className="w-3 h-3 absolute top-1 right-1 opacity-70" />
                <span className="text-xs font-normal">{chipFuera.nombreDia}</span>
                <span className="text-sm font-semibold">{chipFuera.diaMes}</span>
              </Button>
            ) : (
              <Button
                variant="outline"
                className="flex flex-col items-center justify-center min-w-[70px] h-auto py-2"
              >
                <CalendarIcon className="w-4 h-4 mb-0.5" />
                <span className="text-[10px] font-normal leading-tight">Más</span>
                <span className="text-[10px] font-normal leading-tight">fechas</span>
              </Button>
            )}
          </PopoverTrigger>
          <PopoverContent className="w-auto p-0" align="end" side="top">
            <Calendar
              mode="single"
              selected={fechaDate}
              onSelect={handleCalendarSelect}
              locale={es}
              disabled={{ before: hoy }}
            />
          </PopoverContent>
        </Popover>
      </div>
    </div>
  );
}