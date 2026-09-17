import { Button } from '@/components/ui/button';

interface SelectorFechasProps {
  fechaSeleccionada: string;
  onFechaChange: (fecha: string) => void;
}

/**
 * Selector horizontal de 14 días desde hoy.
 * En móvil scroll horizontal, en desktop todos visibles.
 */
export default function SelectorFechas({
  fechaSeleccionada,
  onFechaChange,
}: SelectorFechasProps) {
  const dias: { fecha: string; nombreDia: string; diaMes: string }[] = [];

  const nombresDias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  const nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

  for (let i = 0; i < 14; i++) {
    const d = new Date();
    d.setDate(d.getDate() + i);
    const fechaISO = d.toISOString().split('T')[0];
    dias.push({
      fecha: fechaISO,
      nombreDia: i === 0 ? 'Hoy' : i === 1 ? 'Mañana' : nombresDias[d.getDay()],
      diaMes: `${d.getDate()} ${nombresMeses[d.getMonth()]}`,
    });
  }

  return (
    <div className="overflow-x-auto pb-2 mb-6 -mx-4 px-4">
      <div className="flex gap-2 w-max">
        {dias.map((dia) => {
          const activo = dia.fecha === fechaSeleccionada;
          return (
            <Button
              key={dia.fecha}
              variant={activo ? 'default' : 'outline'}
              onClick={() => onFechaChange(dia.fecha)}
              className={`flex flex-col items-center min-w-[70px] h-auto py-2 ${
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
  );
}