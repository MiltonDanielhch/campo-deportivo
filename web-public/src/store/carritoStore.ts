import { create } from 'zustand';

export interface FranjaCarrito {
  campoId: string;
  campoNombre: string;
  fecha: string;
  horaInicio: string;
  horaFin: string;
  precio: number | null;
}

interface CarritoState {
  franjas: FranjaCarrito[];
  agregarFranja: (franja: FranjaCarrito) => void;
  quitarFranja: (franja: FranjaCarrito) => void;
  limpiar: () => void;
  calcularTotal: () => number;
}

const clave = (f: FranjaCarrito) => `${f.campoId}|${f.fecha}|${f.horaInicio}`;

export const useCarritoStore = create<CarritoState>((set, get) => ({
  franjas: [],
  agregarFranja: (franja) =>
    set((state) =>
      state.franjas.some((f) => clave(f) === clave(franja))
        ? state
        : { franjas: [...state.franjas, franja] },
    ),
  quitarFranja: (franja) =>
    set((state) => ({
      franjas: state.franjas.filter((f) => clave(f) !== clave(franja)),
    })),
  limpiar: () => set({ franjas: [] }),
  calcularTotal: () => get().franjas.reduce((suma, f) => suma + (f.precio ?? 0), 0),
}));
