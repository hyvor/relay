/**
 * Navigation of the Hyvor Relay self-hosting docs (hyvor.com/relay/hosting).
 *
 * This directory is synced to hyvor/core, which renders the docs.
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
          content: await getComponent("Introduction"),
        },
        {
          type: "page",
          slug: "deploy-easy",
          name: s.pages.deployEasy,
          content: await getComponent("EasyDeploy"),
        },
        {
          type: "page",
          slug: "deploy",
          name: s.pages.deploy,
          content: await getComponent("ProdDeploy"),
        },
        {
          type: "page",
          slug: "setup",
          name: s.pages.setup,
          content: await getComponent("Setup"),
        },
        {
          type: "page",
          slug: "management",
          name: s.pages.management,
          content: await getComponent("Management"),
        },
        {
          type: "page",
          slug: "monitoring",
          name: s.pages.monitoring,
          content: await getComponent("Monitoring"),
        },
        {
          type: "page",
          slug: "scaling",
          name: s.pages.scaling,
          content: await getComponent("Scaling"),
        },
        {
          type: "sub-section",
          name: s.sections.upgradeGuides,
          sections: [
            {
              navs: [
            {
              type: "page",
              slug: "upgrade-0.1.0",
              name: s.pages.upgrade010,
              content: await getComponent("Upgrade010"),
            },
              ],
            },
          ],
        },
      ],
    },
    {
      name: s.sections.features,
      navs: [
        {
          type: "page",
          slug: "health-checks",
          name: s.pages.healthChecks,
          content: await getComponent("HealthChecks"),
        },
        {
          type: "page",
          slug: "dns",
          name: s.pages.dns,
          content: await getComponent("Dns"),
        },
      ],
    },
    {
      name: s.sections.misc,
      navs: [
        {
          type: "page",
          slug: "deliverability",
          name: s.pages.deliverability,
          content: await getComponent("Deliverability"),
        },
        {
          type: "page",
          slug: "providers",
          name: s.pages.providers,
          content: await getComponent("EmailProviders"),
        },
        {
          type: "page",
          slug: "env",
          name: s.pages.env,
          content: await getComponent("Env"),
        },
      ],
    },
  ];
}
