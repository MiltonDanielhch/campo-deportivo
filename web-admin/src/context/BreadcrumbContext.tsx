import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  type ReactNode,
} from 'react';

type BreadcrumbOverrides = Record<string, string>;

interface BreadcrumbContextValue {
  overrides: BreadcrumbOverrides;
  setOverride: (path: string, label: string) => void;
  clearOverride: (path: string) => void;
  clearAll: () => void;
}

const BreadcrumbContext = createContext<BreadcrumbContextValue | null>(null);

export function BreadcrumbProvider({ children }: { children: ReactNode }) {
  const [overrides, setOverrides] = useState<BreadcrumbOverrides>({});

  const setOverride = useCallback((path: string, label: string) => {
    setOverrides((prev) => {
      if (prev[path] === label) return prev; // evita re-render si es el mismo
      return { ...prev, [path]: label };
    });
  }, []);

  const clearOverride = useCallback((path: string) => {
    setOverrides((prev) => {
      if (!(path in prev)) return prev;
      const next = { ...prev };
      delete next[path];
      return next;
    });
  }, []);

  const clearAll = useCallback(() => {
    setOverrides((prev) => (Object.keys(prev).length === 0 ? prev : {}));
  }, []);

  return (
    <BreadcrumbContext.Provider
      value={{ overrides, setOverride, clearOverride, clearAll }}
    >
      {children}
    </BreadcrumbContext.Provider>
  );
}

export function useBreadcrumbContext() {
  const ctx = useContext(BreadcrumbContext);
  if (!ctx) {
    throw new Error('useBreadcrumbContext must be used within BreadcrumbProvider');
  }
  return ctx;
}

export function useBreadcrumbOverride(segmentPath: string, label: string) {
  const { setOverride, clearOverride } = useBreadcrumbContext();

  useEffect(() => {
    if (label && label.trim()) {
      setOverride(segmentPath, label);
    }
    return () => clearOverride(segmentPath);
  }, [segmentPath, label, setOverride, clearOverride]);
}
