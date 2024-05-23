import {User} from './User.ts';
import {Section} from './Section.ts';

export type Report = {
  id: number;
  name: string;
  description: string;
  author?: User;
  sections: Section[];
  created_at: string;
  updated_at: string;
  user_ids?: number[];
};
