import * as React from "react"
import { ChevronLeft, ChevronRight } from "lucide-react"
import { DayPicker } from "react-day-picker"
import { cn } from "@/lib/utils"
import { buttonVariants } from "@/components/ui/button"

export type CalendarProps = React.ComponentProps<typeof DayPicker>

function Calendar({
  className,
  classNames,
  showOutsideDays = true,
  ...props
}: CalendarProps) {
  return (
    <DayPicker
      showOutsideDays={showOutsideDays}
      className={cn("p-3", className)}
      classNames={{
        // ⚠️ Claves de react-day-picker v9 (no las de v8)
        months: "relative flex flex-col gap-4 sm:flex-row sm:gap-8",
        month: "flex w-full flex-col gap-4",
        month_caption: "flex h-7 items-center justify-center",
        caption_label: "text-sm font-medium",
        nav: "absolute inset-x-0 top-0 flex w-full items-center justify-between px-1",
        button_previous: cn(
          buttonVariants({ variant: "outline" }),
          "h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100"
        ),
        button_next: cn(
          buttonVariants({ variant: "outline" }),
          "h-7 w-7 bg-transparent p-0 opacity-50 hover:opacity-100"
        ),
        table: "w-full border-collapse",
        weekdays: "flex",
        weekday:
          "flex-1 rounded-md text-[0.8rem] font-normal text-muted-foreground",
        week: "mt-2 flex w-full",
        day: "relative flex-1 p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([aria-selected])]:bg-accent [&:has([aria-selected])]:rounded-md",
        day_button:
          "inline-flex h-9 w-9 items-center justify-center rounded-md p-0 font-normal aria-selected:opacity-100",
        selected:
          "bg-teal-600 text-white hover:bg-teal-700 hover:text-white focus:bg-teal-700 focus:text-white",
        today: "bg-accent text-accent-foreground",
        outside: "text-muted-foreground opacity-50 aria-selected:bg-accent/50",
        disabled: "text-muted-foreground opacity-50",
        hidden: "invisible",
        range_end: "day-range-end",
        range_middle:
          "aria-selected:bg-accent aria-selected:text-accent-foreground",
        ...classNames,
      }}
      components={{
        Chevron: ({ orientation }) => {
          if (orientation === "left") return <ChevronLeft className="h-4 w-4" />
          if (orientation === "right") return <ChevronRight className="h-4 w-4" />
          return null
        },
      }}
      {...props}
    />
  )
}

Calendar.displayName = "Calendar"

export { Calendar }
