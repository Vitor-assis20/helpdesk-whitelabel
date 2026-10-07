/**
 * CSS do widget, dentro do Shadow DOM: não vaza para o site da empresa e o
 * CSS do site não o alcança. A cor da marca entra por variáveis.
 */
export const styles = `
:host { all: initial; }
* { box-sizing: border-box; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
.launcher {
    position: fixed; right: 20px; bottom: 20px; z-index: 2147483000;
    width: 56px; height: 56px; border-radius: 50%; border: 0; cursor: pointer;
    background: var(--brand); color: var(--brand-text);
    box-shadow: 0 6px 20px rgba(0, 0, 0, .2); display: grid; place-items: center;
    transition: transform .15s ease;
}
.launcher:hover { transform: scale(1.06); }
.launcher:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.panel {
    position: fixed; right: 20px; bottom: 88px; z-index: 2147483000;
    width: min(380px, calc(100vw - 40px)); height: min(560px, calc(100vh - 120px));
    background: #fff; color: #18181b; border-radius: 16px; overflow: hidden;
    box-shadow: 0 12px 40px rgba(0, 0, 0, .25); display: flex; flex-direction: column;
}
.panel[hidden] { display: none; }
.header { background: var(--brand); color: var(--brand-text); padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; }
.header strong { font-size: 15px; }
.header small { display: block; opacity: .85; font-size: 12px; margin-top: 2px; }
.icon-button { background: transparent; border: 0; color: inherit; cursor: pointer; padding: 4px; border-radius: 6px; font-size: 18px; line-height: 1; }
.body { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; background: #fafafa; }
.footer { border-top: 1px solid #e4e4e7; padding: 10px; background: #fff; }
label { font-size: 12px; font-weight: 600; color: #3f3f46; display: grid; gap: 4px; }
input, textarea { width: 100%; border: 1px solid #d4d4d8; border-radius: 8px; padding: 8px 10px; font-size: 14px; color: #18181b; background: #fff; }
textarea { resize: vertical; min-height: 72px; }
.primary { width: 100%; border: 0; border-radius: 8px; padding: 10px; font-size: 14px; font-weight: 600; cursor: pointer; background: var(--brand); color: var(--brand-text); }
.primary:disabled { opacity: .6; cursor: default; }
.link { background: none; border: 0; color: #52525b; font-size: 12px; cursor: pointer; text-decoration: underline; padding: 0; }
.error { color: #dc2626; font-size: 12px; }
.intro { font-size: 14px; color: #3f3f46; margin: 0; }
.bubble { max-width: 85%; padding: 8px 12px; border-radius: 12px; font-size: 14px; white-space: pre-wrap; word-wrap: break-word; }
.bubble small { display: block; font-size: 11px; opacity: .75; margin-bottom: 2px; }
.mine { align-self: flex-end; background: var(--brand); color: var(--brand-text); }
.theirs { align-self: flex-start; background: #fff; border: 1px solid #e4e4e7; }
.status { font-size: 12px; color: #52525b; text-align: center; }
.reply { display: flex; gap: 8px; align-items: flex-end; }
.reply textarea { min-height: 40px; }
.reply .primary { width: auto; padding: 10px 14px; }
.rating { margin-bottom: 8px; }
.rating .status { margin: 0 0 6px; }
.scores { display: flex; justify-content: center; gap: 6px; }
.score { font-size: 22px; line-height: 1; padding: 6px 8px; border: 1px solid #e4e4e7; border-radius: 10px; background: #fff; cursor: pointer; transition: transform .1s ease; }
.score:hover { transform: scale(1.12); border-color: var(--brand); }
.score:disabled { opacity: .5; cursor: default; transform: none; }
`;
