import { createContext } from 'react'

// The page the viewer shows, and a way to open it on another page, for every page reference on the result.
export interface Viewer {
  page: number
  openPage: (page: number) => void
}

export const ViewerContext = createContext<Viewer | null>(null)
