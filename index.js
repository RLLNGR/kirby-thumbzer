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
        <k-box theme="info" style="margin-top: 0.5rem">
          <k-button
            v-if="link"
            :link="link"
            target="_blank"
            icon="preview"
            size="sm"
          >
            Test ICC Profile
          </k-button>
        </k-box>
      `
    }
  }
});
