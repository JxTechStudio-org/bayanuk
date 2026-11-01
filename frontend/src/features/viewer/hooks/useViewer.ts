import { useContext } from 'react'
import { ViewerContext } from '../viewerContext'

// The viewer of the page, or null where there is none (a component shown on its own, in a test for example).
export function useViewer() {
  return useContext(ViewerContext)
}
