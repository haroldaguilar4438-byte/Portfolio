/* ============================================================
   PORTFOLIO CONTENT CONFIG — EDIT EVERYTHING HERE
   Replace every [PLACEHOLDER] with your real information.
   This file is the single source of truth for easy updates.
   ============================================================ */
const PORTFOLIO = {
  personal: {
    name: "Harold R. Aguilar",
    initials: "H.A.",
    title: "BSIT Student / Aspiring IT Professional",
    tagline: "Designing and building practical digital systems with a focus on usability, reliability, and modern technology.",
    location: "San Antonio, Paranaque City",
    email: "aguilarhrld@gmail.com",
    github: "https://github.com/haroldaguilar4438-byte",
    linkedin: "https://www.linkedin.com/in/haroldaguilar",
    availability: "Open to internship / entry-level roles",
    resumeFile: "assets/resume.pdf" // replace with your real resume PDF path
  },
  // Beginner-friendly starter set — edit to match real experience.
  // Levels: Fundamentals / Familiar / Intermediate / Advanced
  skills: {
    note: "Edit categories and items to match your real experience.",
    categories: [
      { name: "Frontend Development", level: "Fundamentals", items: ["HTML", "CSS", "JavaScript", "Responsive design"] },
      { name: "Backend Development", level: "Familiar", items: ["PHP fundamentals", "Form handling", "REST basics"] },
      { name: "Database", level: "Familiar", items: ["MySQL fundamentals", "SQL queries", "CRUD operations"] },
      { name: "Programming", level: "Familiar", items: ["JavaScript", "PHP", "Java basics"] },
      { name: "Tools & Platforms", level: "Fundamentals", items: ["VS Code", "Git", "GitHub", "XAMPP"] },
      { name: "UI/UX", level: "Familiar", items: ["Responsive design", "Accessibility basics"] },
      { name: "Cybersecurity Awareness", level: "Familiar", items: ["Auth basics", "Input validation", "Access control"] },
      { name: "AI-Assisted Development", level: "Familiar", items: ["Prompting", "Code review", "Verification & testing"] }
    ]
  },
  socials: [
    { label: "GitHub", value: "https://github.com/haroldaguilar4438-byte", hint: "Repositories, commits, project code" },
    { label: "LinkedIn", value: "https://www.linkedin.com/in/haroldaguilar", hint: "Headline, skills, projects, education" },
    { label: "Email", value: "aguilarhrld@gmail.com", hint: "Professional contact" }
  ],
  // ============================================================
  // PROJECT REGISTRY
  // Add or edit projects here. Each entry renders in the Projects section.
  //   published:false  -> reserved slot, stays hidden until real details exist
  //   technologies     -> only list tech you have VERIFIED (check the live site/source)
  //   url              -> real link only, never a placeholder
  //   screenshot       -> path under assets/projects/ (optional)
  // ============================================================
  projects: [
    {
      number: 1,
      title: "EduPortal — Web-Based Integrated School Management System",
      category: "Academic / Capstone Project",
      type: "Team Project",
      url: "https://skyblue-sardine-644746.hostingersite.com",
      // Real screenshot captured from the live site (regenerate any time):
      //   chrome --headless=new --window-size=1440,1000 --screenshot=assets/projects/eduportal.png <url>
      screenshot: "assets/projects/eduportal.png",
      technologies: ["HTML", "CSS", "Tailwind CSS", "Bootstrap 5.3", "JavaScript"],
      published: true
    },
    {
      number: 2,
      title: "", // TODO: add title when your groupmate's project is ready
      category: "",
      type: "",
      url: "",
      screenshot: "",
      technologies: [],
      published: false // hidden until real details are filled in
    },
    {
      number: 3,
      title: "",
      category: "",
      type: "",
      url: "",
      screenshot: "",
      technologies: [],
      published: false // hidden until real details are filled in
    }
  ]
};
// Expose for main.js (and future rendering)
window.PORTFOLIO = PORTFOLIO;
