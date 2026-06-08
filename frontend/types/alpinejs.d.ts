declare module 'alpinejs' {
  type AlpineComponentFactory<T = unknown> = (...args: any[]) => T;

  interface AlpineInstance {
    data(name: string, callback: AlpineComponentFactory): void;
    start(): void;
  }

  const Alpine: AlpineInstance;
  export default Alpine;
}
