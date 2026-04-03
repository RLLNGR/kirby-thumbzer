panel.plugin("rllngr/kirby-thumbzer", {
  sections: {
    "thumbzer-icc-button": {
      data() {
        return { link: null };
      },
      async created() {
        const data = await this.load();
        this.link = data.link;
      },
      template: `
        <div style="padding-top: 0.5rem">
          <a
            v-if="link"
            :href="link"
            target="_blank"
            style="
              display: inline-flex;
              align-items: center;
              gap: 0.4rem;
              font-size: 0.75rem;
              color: var(--color-gray-500, #888);
              text-decoration: none;
              padding: 0.35rem 0.65rem;
              border: 1px solid var(--color-gray-200, #e0e0e0);
              border-radius: 3px;
              background: var(--color-white, #fff);
              transition: border-color 0.15s, color 0.15s;
            "
            onmouseover="this.style.borderColor='var(--color-gray-400, #aaa)';this.style.color='var(--color-gray-700, #555)'"
            onmouseout="this.style.borderColor='var(--color-gray-200, #e0e0e0)';this.style.color='var(--color-gray-500, #888)'"
          >
            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 2"/></svg>
            Comparer les profils ICC
          </a>
        </div>
      `
    }
  }
});
