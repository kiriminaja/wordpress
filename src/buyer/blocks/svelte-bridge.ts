import { mount, unmount } from 'svelte';
import { writable } from 'svelte/store';
import type { Component } from 'svelte';
import type { Readable } from 'svelte/store';
import MapInformation from '../components/MapInformation.svelte';
import PinStatus from '../components/PinStatus.svelte';

export interface MapInformationModel {
  badge: string;
  coverage: string;
  hasCoverage: boolean;
}
export interface PinStatusModel {
  complete: boolean;
  text: string;
}
interface NativeElement {
  createElement(type: unknown, props: unknown, ...children: unknown[]): unknown;
  useRef<T>(initial: T): { current: T };
  useEffect(effect: () => void | (() => void), dependencies: unknown[]): void;
}
/** React retains the exact existing host element and attributes. Svelte owns only
 * the presentational children; it never owns Leaflet, native selects or stores. */
function presentation<Model>(
  element: NativeElement,
  component: Component<{ model: Readable<Model> }>,
) {
  return function Presentation(props: { model: Model; attributes: Record<string, unknown> }) {
    const node = element.useRef<HTMLElement | null>(null);
    const model = element.useRef(writable(props.model));
    element.useEffect(() => {
      if (!node.current) return;
      const instance = mount(component, { target: node.current, props: { model: model.current } });
      return () => {
        void unmount(instance);
      };
    }, []);
    element.useEffect(() => {
      model.current.set(props.model);
    }, [props.model]);
    return element.createElement('div', { ...props.attributes, ref: node });
  };
}
export function createMapPresentation(element: NativeElement) {
  return {
    Information: presentation(element, MapInformation),
    Status: presentation(element, PinStatus),
  };
}
