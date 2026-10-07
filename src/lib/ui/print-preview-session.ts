export type PreviewState = { url: string; loading: boolean; error: string };

/** One active preview request; cancellation also invalidates servers that ignore abort. */
export class PrintPreviewSession {
  private generation = 0;
  private controller?: AbortController;

  constructor(private readonly update: (state: PreviewState) => void) {}

  cancel(): void {
    this.generation += 1;
    this.controller?.abort();
    this.controller = undefined;
    this.update({ url: '', loading: false, error: '' });
  }

  async load(
    request: (signal: AbortSignal) => Promise<string>,
    fallbackError: string,
    onError: (error: string) => void,
  ): Promise<void> {
    this.cancel();
    const generation = this.generation;
    const controller = new AbortController();
    this.controller = controller;
    this.update({ url: '', loading: true, error: '' });
    try {
      const url = await request(controller.signal);
      if (generation !== this.generation || controller.signal.aborted) return;
      this.update({ url, loading: false, error: '' });
    } catch (cause) {
      if (generation !== this.generation || controller.signal.aborted) return;
      const error = cause instanceof Error ? cause.message : fallbackError;
      this.update({ url: '', loading: false, error });
      onError(error);
    } finally {
      if (generation === this.generation) this.controller = undefined;
    }
  }
}
