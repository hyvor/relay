/**
 * Navigation of the Hyvor Relay docs (hyvor.com/relay/docs).
 *
 * This directory is synced to hyvor/marketing, which renders the docs.
 * Keep it self-contained: only import from within this directory,
 * `svelte`, `@hyvor/design`, and `@hyvor/icons`.
 */
import type { NavSectionConfig } from "@hyvor/design/marketing";
import type { Component } from "svelte";
import en from "./locale/en.json";

export const LANGUAGES = ["en"];

const STRINGS: Record<string, typeof en> = { en };

const PAGES = import.meta.glob<{ default: Component }>("./*/*.md");

// loads a page in the given language, falling back to English if it is not translated yet
async function loadPage(lang: string, file: string): Promise<Component> {
  const loader = PAGES[`./${lang}/${file}.md`] ?? PAGES[`./en/${file}.md`];
  if (!loader) {
    throw new Error(`Docs page not found: ${file}`);
  }
  return (await loader()).default;
}

export async function getSections(lang: string): Promise<NavSectionConfig[]> {
  const s = STRINGS[lang] ?? en;
  const getComponent = (file: string) => loadPage(lang, file);

  return [
    {
      name: "",
      navs: [
        {
          type: "page",
          slug: "",
          name: s.pages.introduction,
          description: s.descriptions.introduction,
          content: await getComponent("Introduction"),
        },
        {
          type: "page",
          slug: "domains",
          name: s.pages.domains,
          description: s.descriptions.domains,
          content: await getComponent("Domains"),
        },
        {
          type: "page",
          slug: "send-emails",
          name: s.pages.sendEmails,
          description: s.descriptions.sendEmails,
          content: await getComponent("SendEmails"),
        },
        {
          type: "page",
          slug: "send-emails-smtp",
          name: s.pages.sendEmailsSmtp,
          description: s.descriptions.sendEmailsSmtp,
          content: await getComponent("SendEmailsSmtp"),
        },
      ],
    },
    {
      name: s.sections.api,
      navs: [
        {
          type: "page",
          slug: "api-console",
          name: s.pages.consoleApi,
          description: s.descriptions.consoleApi,
          content: await getComponent("ConsoleApi"),
        },
        {
          type: "page",
          slug: "webhooks",
          name: s.pages.webhooks,
          description: s.descriptions.webhooks,
          content: await getComponent("Webhooks"),
        },
      ],
    },
  ];
}
