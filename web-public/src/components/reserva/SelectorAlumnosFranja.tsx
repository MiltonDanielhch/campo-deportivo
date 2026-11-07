import { Button } from '@/components/ui/button';
import type { Alumno } from '@/services/ciudadanoService';

interface Props {
  etiqueta: string;
  alumnos: Alumno[];
  seleccionados: string[];
  onChange: (ids: string[]) => void;
}

/**
 * [Fase 6b] Multi-select de alumnos para UNA franja. Patrón toggle (Botón
 * lleno/vacío) para no depender de un primitivo Checkbox que puede no existir
 * en web-public. Seleccionado = asiste. Vacío permitido (reserva sin alumnos,
 * espejo de ConvenioAlumnosVentanillaTest "reserva sin alumnos sigue creando").
 */
export default function SelectorAlumnosFranja({ etiqueta, alumnos, seleccionados, onChange }: Props) {
  const alternar = (id: string) => {
    onChange(
      seleccionados.includes(id)
        ? seleccionados.filter((x) => x !== id)
        : [...seleccionados, id],
    );
  };

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium text-slate-700">{etiqueta}</p>
      <div className="flex flex-wrap gap-2">
        {alumnos.map((a) => {
          const on = seleccionados.includes(a.id);
          return (
            <Button
              key={a.id}
              type="button"
              size="sm"
              variant={on ? 'default' : 'outline'}
              onClick={() => alternar(a.id)}
              className={on ? 'bg-teal-600 text-white hover:bg-teal-700' : ''}
            >
              {a.nombre_completo}
              <span className="ml-1.5 font-mono text-[10px] opacity-70">{a.ci}</span>
            </Button>
          );
        })}
      </div>
    </div>
  );
}
