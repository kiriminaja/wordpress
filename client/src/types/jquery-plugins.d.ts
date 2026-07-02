type KiriofJqueryValue = any;

interface JQuery<TElement = HTMLElement> {
  [key: string]: KiriofJqueryValue;
  length: number;
  val(): string;
  val(value: string | number | string[]): JQuery<TElement>;
  data(key: string): KiriofJqueryValue;
  data(key: string, value: KiriofJqueryValue): JQuery<TElement>;
  remove(): JQuery<TElement>;
  on(...args: KiriofJqueryValue[]): JQuery<TElement>;
  off(...args: KiriofJqueryValue[]): JQuery<TElement>;
  one(...args: KiriofJqueryValue[]): JQuery<TElement>;
  trigger(...args: KiriofJqueryValue[]): JQuery<TElement>;
  each(callback: (this: TElement, index: number, element: TElement) => void): JQuery<TElement>;
  map<T>(callback: (this: TElement, index: number, element: TElement) => T): T[];
  first(): JQuery<TElement>;
  find(selector: string): JQuery;
  filter(...args: KiriofJqueryValue[]): JQuery<TElement>;
  closest(selector: string): JQuery;
  parent(): JQuery;
  after(...args: KiriofJqueryValue[]): JQuery<TElement>;
  append(...args: KiriofJqueryValue[]): JQuery<TElement>;
  appendTo(target: JQuery | HTMLElement | string): JQuery<TElement>;
  empty(): JQuery<TElement>;
  addClass(className: string): JQuery<TElement>;
  removeClass(className: string): JQuery<TElement>;
  toggle(show?: boolean): JQuery<TElement>;
  hide(): JQuery<TElement>;
  show(): JQuery<TElement>;
  css(...args: KiriofJqueryValue[]): JQuery<TElement>;
  prop(...args: KiriofJqueryValue[]): KiriofJqueryValue;
  attr(...args: KiriofJqueryValue[]): KiriofJqueryValue;
  text(...args: KiriofJqueryValue[]): KiriofJqueryValue;
  html(...args: KiriofJqueryValue[]): KiriofJqueryValue;
  is(selector: string): boolean;
  get(index: number): TElement | undefined;
  block(options?: Record<string, unknown>): JQuery<TElement>;
  unblock(): JQuery<TElement>;
  qrcode(options: Record<string, unknown>): JQuery<TElement>;
  select2(options?: Record<string, unknown> | string): JQuery<TElement>;
  selectWoo(options?: Record<string, unknown> | string): JQuery<TElement>;
  WCBackboneModal(options: Record<string, unknown>): JQuery<TElement>;
}

interface JQueryStatic {
  <TElement = HTMLElement>(selector?: KiriofJqueryValue, context?: KiriofJqueryValue): JQuery<TElement>;
  fn: Record<string, KiriofJqueryValue>;
  ajax(options: Record<string, KiriofJqueryValue>): KiriofJqueryValue;
  get(url: string, data?: Record<string, KiriofJqueryValue>, success?: (data: KiriofJqueryValue) => void): KiriofJqueryValue;
  post(url: string, data?: Record<string, KiriofJqueryValue>, success?: (data: KiriofJqueryValue) => void): KiriofJqueryValue;
  each<T>(items: T[] | Record<string, T>, callback: (index: string | number, value: T) => KiriofJqueryValue): void;
  map<T = KiriofJqueryValue, U = KiriofJqueryValue>(items: T[] | Record<string, T>, callback: (value: T, index: string | number) => U): U[];
  extend<T extends object, U extends object>(target: T, source: U): T & U;
  blockUI?: (options?: Record<string, unknown>) => void;
  unblockUI?: () => void;
}

interface JQueryEventObject {
  params?: {
    data?: {
      id?: string;
      text?: string;
    };
  };
}

declare const jQuery: JQueryStatic;
declare const $: JQueryStatic;

interface EventTarget {
  closest(selectors: string): Element | null;
}

interface Element {
  value: string;
}

interface HTMLElement {
  value: string;
  required: boolean;
  setCustomValidity(error: string): void;
}
