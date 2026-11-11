import { Minus, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function StepperCantidad({
  valor,
  onChange,
  ariaLabel,
}: {
  valor: number;
  onChange: (v: number) => void;
  ariaLabel: string;
}) {
  return (
    <div className="flex shrink-0 items-center gap-1 rounded-lg border bg-background p-0.5">
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="size-7"
        disabled={valor <= 1}
        onClick={() => onChange(valor - 1)}
        aria-label={`Restar ${ariaLabel}`}
      >
        <Minus className="size-3.5" />
      </Button>
      <Input
        type="number"
        min={1}
        max={100}
        value={valor}
        onChange={(e) => onChange(Number(e.target.value))}
        className="h-7 w-12 border-0 bg-transparent px-0 text-center text-sm tabular-nums focus-visible:ring-0"
        aria-label={ariaLabel}
      />
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="size-7"
        disabled={valor >= 100}
        onClick={() => onChange(valor + 1)}
        aria-label={`Sumar ${ariaLabel}`}
      >
        <Plus className="size-3.5" />
      </Button>
    </div>
  );
}
